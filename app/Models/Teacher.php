<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Teacher extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'name',
        'email',
        'phone',
        'qualification',
        'subject_specialization',
        'date_of_joining',
        'salary',
        'address',
        'aadhar_number',
        'bank_account_number',
        'ifsc_code',
        'status',
        'experience_details',
        'profile_image'
    ];

    protected $casts = [
        'date_of_joining' => 'date',
        'salary' => 'decimal:2'
    ];

    // Validation rules for storing teacher
    public static function storeRules()
    {
        return [
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:teachers',
            'phone' => 'required|digits:10|unique:teachers',
            'qualification' => 'required|string|max:255',
            'subject_specialization' => 'required|string|max:255',
            'date_of_joining' => 'required|date',
            'salary' => 'required|numeric|min:0',
            'address' => 'required|string',
            'aadhar_number' => 'required|digits:12|unique:teachers',
            'status' => 'required|in:active,inactive,on_leave'
        ];
    }

    // Validation rules for updating teacher
    public static function updateRules($id)
    {
        return [
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:teachers,email,' . $id,
            'phone' => 'required|digits:10|unique:teachers,phone,' . $id,
            'qualification' => 'required|string|max:255',
            'subject_specialization' => 'required|string|max:255',
            'date_of_joining' => 'required|date',
            'salary' => 'required|numeric|min:0',
            'address' => 'required|string',
            'aadhar_number' => 'required|digits:12|unique:teachers,aadhar_number,' . $id,
            'status' => 'required|in:active,inactive,on_leave'
        ];
    }

    // Calculate experience in years
    public function getExperienceAttribute()
    {
        $joiningDate = \Carbon\Carbon::parse($this->date_of_joining);
        $now = \Carbon\Carbon::now();
        return $joiningDate->diffInYears($now);
    }
}