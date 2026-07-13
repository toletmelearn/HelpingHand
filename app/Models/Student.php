<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use App\Models\User;
use App\Models\Guardian;
use App\Models\Attendance;
use App\Models\Fee;
use App\Models\Result;
use App\Traits\Auditable;
use App\Models\SchoolClass;
use App\Models\FeeCollection;
use App\Models\StudentFeeAssignment;

class Student extends Authenticatable
{
    use HasFactory, SoftDeletes, Auditable, Notifiable;

    protected static function booted()
    {
        static::creating(function ($student) {
            if (\Illuminate\Support\Facades\Schema::hasColumn('students', 'phone') && is_null($student->phone)) {
                $student->phone = '';
            }
        });

        // schoolClass() picks class_id vs school_class_id per-row based on
        // which is null -- correct for a single lazy-loaded model, but
        // Eloquent eager loading (Student::with('schoolClass')) resolves the
        // foreign key column ONCE for the whole batch, so any row where only
        // one of the two columns is set gets silently matched on the wrong
        // column and shows no class at all. Keeping both columns in sync at
        // write time, for every path (admin form, bulk import, anything
        // added later), makes that inconsistency impossible rather than
        // relying on every future write path remembering to set both.
        static::saving(function ($student) {
            // Guard against test/mocked environments with a hand-built
            // students table that predates this column (several FeeFinance
            // tests build their own minimal schema without it).
            if (!\Illuminate\Support\Facades\Schema::hasColumn('students', 'school_class_id')
                || !\Illuminate\Support\Facades\Schema::hasColumn('students', 'class_id')) {
                return;
            }

            if ($student->class_id !== null && $student->school_class_id === null) {
                // Unlike school_class_id, class_id has no foreign key
                // constraint -- some legacy/loosely-normalized rows carry a
                // class_id that isn't a real school_classes row. Only copy
                // it across when it actually resolves, so this sync can
                // never itself cause a constraint-violation write failure.
                if (\App\Models\SchoolClass::whereKey($student->class_id)->exists()) {
                    $student->school_class_id = $student->class_id;
                }
            } elseif ($student->school_class_id !== null && $student->class_id === null) {
                $student->class_id = $student->school_class_id;
            }
        });

        static::saved(function ($student) {
            // Guard against running in mocked test environments without all database tables/columns
            if (!\Illuminate\Support\Facades\Schema::hasTable('parents') ||
                !\Illuminate\Support\Facades\Schema::hasColumn('students', 'phone') ||
                !\Illuminate\Support\Facades\Schema::hasColumn('students', 'mobile') ||
                !\Illuminate\Support\Facades\Schema::hasColumn('students', 'admission_no')) {
                return;
            }

            $parent = null;

            // 1. Try to resolve via already assigned parent_id
            if ($student->parent_id) {
                $parent = \App\Models\ParentModel::find($student->parent_id);
            }

            // 2. Try to resolve via matching phone number (deduplication)
            $phoneLookup = $student->mobile ?: $student->phone;
            if (!$parent && !empty($phoneLookup)) {
                $parent = \App\Models\ParentModel::where(function($query) use ($phoneLookup) {
                    $query->where('phone', $phoneLookup)->orWhere('mobile', $phoneLookup);
                })->first();
            }

            // 3. Fallback to legacy student_id column mapping
            if (!$parent) {
                $parent = \App\Models\ParentModel::where('student_id', $student->id)->first();
            }

            if (!$parent) {
                // 4. Create new Parent Model with a random one-time password (never a fixed default)
                $parent = \App\Models\ParentModel::create([
                    'student_id' => $student->id,
                    'name' => 'Parent of ' . $student->name,
                    'email' => 'parent.' . $student->id . '@example.com',
                    'phone' => $student->mobile ?: $student->phone,
                    'mobile' => $student->mobile ?: $student->phone,
                    'admission_number' => $student->admission_no,
                    'password' => bcrypt(\Illuminate\Support\Str::random(12)),
                    'must_reset_password' => true,
                ]);
            } else {
                // Update parent details if it's the primary student link
                if ($parent->student_id === $student->id || !$parent->student_id) {
                    $updateData = [
                        'phone' => $student->mobile ?: $student->phone,
                        'mobile' => $student->mobile ?: $student->phone,
                        'admission_number' => $student->admission_no,
                    ];
                    if (!$parent->student_id) {
                        $updateData['student_id'] = $student->id;
                    }
                    $parent->update($updateData);
                }
            }

            if (\Illuminate\Support\Facades\Schema::hasColumn('students', 'parent_id')) {
                // Ensure the student's parent_id column points to this parent
                if ($student->parent_id !== $parent->id) {
                    \Illuminate\Support\Facades\DB::table('students')
                        ->where('id', $student->id)
                        ->update(['parent_id' => $parent->id]);
                    $student->setAttribute('parent_id', $parent->id);
                }
            }

            if (\Illuminate\Support\Facades\Schema::hasTable('student_financial_accounts')) {
                \App\Models\StudentFinancialAccount::firstOrCreate([
                    'student_id' => $student->id
                ], [
                    'account_no' => 'FIN-' . str_pad($student->id, 6, '0', STR_PAD_LEFT),
                    'status' => 'active',
                    'created_by' => auth('web')->id() ?? null,
                    'ip_address' => request()->ip()
                ]);
            }

            // Sibling detection by phone -- deliberately does NOT set
            // family_id directly (unlike the parent_id dedup above, which
            // is a safe merge). A shared phone number could be
            // coincidental (e.g. reused mobile) or a genuine sibling, so
            // this only queues a suggestion for an admin to confirm or
            // dismiss (family_link_suggestions), same reasoning as why
            // sibling matching needs "admin confirmation" per spec.
            if (\Illuminate\Support\Facades\Schema::hasTable('family_link_suggestions') && empty($student->family_id)) {
                $alreadySuggested = \App\Models\FamilyLinkSuggestion::where('student_id', $student->id)->exists();

                if (!$alreadySuggested && !empty($phoneLookup)) {
                    $candidate = \App\Models\Student::where('id', '!=', $student->id)
                        ->whereNull('deleted_at')
                        ->where(function ($q) use ($phoneLookup) {
                            $q->where('mobile', $phoneLookup)->orWhere('phone', $phoneLookup);
                        })
                        ->first();

                    if ($candidate) {
                        \App\Models\FamilyLinkSuggestion::create([
                            'student_id' => $student->id,
                            'matched_family_id' => $candidate->family_id,
                            'candidate_student_id' => $candidate->family_id ? null : $candidate->id,
                            'match_basis' => 'mobile',
                            'matched_value' => $phoneLookup,
                            'status' => 'pending',
                        ]);
                    }
                }
            }
        });

        static::deleted(function ($student) {
            if (!\Illuminate\Support\Facades\Schema::hasTable('parents')) {
                return;
            }
            \App\Models\ParentModel::where('student_id', $student->id)->delete();
        });
    }

    protected $fillable = [
        'name', 'father_name', 'mother_name', 'date_of_birth', 'aadhar_number',
        'admission_no', 'admission_session_id', 'phone', 'mobile', 'gender', 'category', 'class', 'section', 'roll_number',
        'religion', 'caste', 'blood_group', 'address', 'user_id', 'is_verified',
        'guardian_name', 'class_id', 'section_id', 'photo', 'family_id'
    ];
    
    protected $casts = [
        'date_of_birth' => 'date',
        'admission_no' => 'string',
        'mobile' => 'string',
        'class_id' => 'integer',
        'guardian_name' => 'string'
    ];
    
    protected $dates = ['date_of_birth'];

    // Hidden attributes for security
    protected $hidden = [
        'created_at', 'updated_at', 'deleted_at'
    ];

    // Append calculated attributes
    protected $appends = ['full_name', 'age'];


    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function staffParent()
    {
        return $this->belongsTo(User::class, 'staff_user_id');
    }

    public function guardian()
    {
        return $this->belongsToMany(Guardian::class, 'student_guardian', 'student_id', 'guardian_id');
    }

    public function attendances()
    {
        return $this->hasMany(Attendance::class);
    }

    public function fees()
    {
        return $this->hasMany(Fee::class);
    }

    public function results()
    {
        return $this->hasMany(Result::class);
    }

    public function documents()
    {
        return $this->hasMany(StudentDocument::class);
    }

    public function admissionSession()
    {
        return $this->belongsTo(AcademicSession::class, 'admission_session_id');
    }

    /**
     * True only if this student was admitted during the given academic session --
     * i.e. they are a genuinely new admission for that session, not a promoted
     * or continuing student. Students admitted before this field existed have a
     * null admission_session_id and are treated as continuing (never "new").
     */
    public function isNewAdmissionFor(AcademicSession $session): bool
    {
        return $this->admission_session_id !== null
            && (int) $this->admission_session_id === (int) $session->id;
    }

    public function schoolClass()
    {
        $foreignKey = ($this->school_class_id === null && $this->class_id !== null) ? 'class_id' : 'school_class_id';
        return $this->belongsTo(SchoolClass::class, $foreignKey);
    }
    
    public function class()
    {
        $foreignKey = ($this->school_class_id === null && $this->class_id !== null) ? 'class_id' : 'school_class_id';
        return $this->belongsTo(SchoolClass::class, $foreignKey);
    }
    
    public function section()
    {
        return $this->belongsTo(Section::class, 'section_id');
    }

    public function schoolSection()
    {
        return $this->belongsTo(Section::class, 'section_id');
    }

    public function canonicalClassId(): ?int
    {
        if ($this->class_id !== null) {
            return (int) $this->class_id;
        }

        if ($this->school_class_id !== null) {
            return (int) $this->school_class_id;
        }

        return null;
    }

    public function hasClassIdConflict(): bool
    {
        return $this->class_id !== null
            && $this->school_class_id !== null
            && (int) $this->class_id !== (int) $this->school_class_id;
    }

    public function resolveCanonicalSchoolClass(): ?SchoolClass
    {
        $classId = $this->canonicalClassId();

        return $classId ? SchoolClass::find($classId) : null;
    }

    public function classCompatibilityStatus(): array
    {
        return [
            'canonical_class_id' => $this->canonicalClassId(),
            'class_id' => $this->class_id !== null ? (int) $this->class_id : null,
            'school_class_id' => $this->school_class_id !== null ? (int) $this->school_class_id : null,
            'string_class' => $this->class,
            'has_conflict' => $this->hasClassIdConflict(),
            'source' => $this->classCompatibilitySource(),
        ];
    }

    private function classCompatibilitySource(): string
    {
        if ($this->class_id !== null) {
            return 'class_id';
        }

        if ($this->school_class_id !== null) {
            return 'school_class_id_fallback';
        }

        return 'none';
    }
    
    public function feeCollections()
    {
        return $this->hasMany(FeeCollection::class);
    }
    
    public function feeAssignments()
    {
        return $this->hasMany(StudentFeeAssignment::class);
    }
    
    // Relationship with Parent
    public function parent()
    {
        return $this->belongsTo(ParentModel::class, 'parent_id');
    }

    public function family()
    {
        return $this->belongsTo(Family::class, 'family_id');
    }

    public function transportAssignment()
    {
        return $this->hasOne(StudentTransport::class, 'student_id');
    }

    public function gatePasses()
    {
        return $this->hasMany(GatePass::class, 'student_id');
    }

    public function transportDues()
    {
        return $this->hasMany(StudentTransportDue::class, 'student_id');
    }

    public function discountsApplied()
    {
        return $this->hasMany(StudentDiscountApplied::class, 'student_id');
    }

    public function ledgerRecords()
    {
        return $this->hasMany(StudentFeeLedger::class, 'student_id');
    }

    public function financialAccount()
    {
        return $this->hasOne(StudentFinancialAccount::class, 'student_id');
    }


    // Accessors
    public function getFullNameAttribute()
    {
        return $this->name . ' (' . $this->roll_number . ')';
    }

    public function getAgeAttribute()
    {
        return $this->date_of_birth ? $this->date_of_birth->age : null;
    }

    public function getPhotoUrlAttribute()
    {
        return $this->photo ? asset('storage/' . $this->photo) : asset('images/default-avatar.png');
    }

    // Scopes
    public function scopeActive($query)
    {
        return $query->whereNull('deleted_at');
    }

    public function scopeInClass($query, $class)
    {
        return $query->where('class', $class);
    }

    /**
     * Get statistics for the students dashboard
     */
    /**
     * Get the password for the user.
     * For parent login, we use a default password.
     */
    public function getAuthPassword()
    {
        return '123456'; // Default password for all parents
    }
    
    /**
     * Get the column name for the "remember me" token.
     */
    public function getRememberTokenName()
    {
        return 'remember_token';
    }
    
    public static function getStatistics()
    {
        return cache()->remember('student_statistics', 3600, function() {
            $total = self::count();
            $male = self::where('gender', 'male')->count();
            $female = self::where('gender', 'female')->count();
            $other = self::where('gender', 'other')->count();

            $malePercentage = $total > 0 ? round(($male / $total) * 100, 2) : 0;
            $femalePercentage = $total > 0 ? round(($female / $total) * 100, 2) : 0;
            $otherPercentage = $total > 0 ? round(($other / $total) * 100, 2) : 0;

            // Class-wise distribution
            $classWise = self::selectRaw(' 
                class, 
                COUNT(*) as total,
                SUM(CASE WHEN gender = "male" THEN 1 ELSE 0 END) as male,
                SUM(CASE WHEN gender = "female" THEN 1 ELSE 0 END) as female,
                SUM(CASE WHEN gender = "other" THEN 1 ELSE 0 END) as other
            ')
            ->whereNull('deleted_at')
            ->groupBy('class')
            ->orderBy('class')
            ->get();

            // Category-wise distribution
            $categories = ['General', 'OBC', 'SC', 'ST'];
            $categoryWise = [];
            foreach ($categories as $category) {
                $categoryData = self::where('category', $category)->get();
                $categoryWise[$category] = [
                    'total' => $categoryData->count(),
                    'male' => $categoryData->where('gender', 'male')->count(),
                    'female' => $categoryData->where('gender', 'female')->count(),
                    'other' => $categoryData->where('gender', 'other')->count(),
                ];
            }

            return [
                'total' => $total,
                'male' => $male,
                'female' => $female,
                'other' => $other,
                'male_percentage' => $malePercentage,
                'female_percentage' => $femalePercentage,
                'other_percentage' => $otherPercentage,
                'gender_wise' => [
                    'male' => $male,
                    'female' => $female,
                    'other' => $other,
                ],
                'class_wise' => $classWise,
                'category_wise' => $categoryWise,
            ];
        });
    }

    /**
     * Mutator to handle "unknown" blood group conversion to null.
     */
    public function setBloodGroupAttribute($value)
    {
        $this->attributes['blood_group'] = ($value === 'unknown' || $value === '') ? null : $value;
    }
}
