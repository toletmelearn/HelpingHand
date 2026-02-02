<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use App\Traits\Auditable;

class Book extends Model
{
    use Auditable;
    protected $fillable = [
        'book_name',
        'isbn',
        'author',
        'publisher',
        'subject',
        'class_grade',
        'total_quantity',
        'rack_shelf_number',
        'cover_image',
        'is_active',
    ];

    public function bookIssues(): HasMany
    {
        return $this->hasMany(BookIssue::class);
    }

    public function getAvailableCopiesAttribute(): int
    {
        $issuedCopies = $this->bookIssues()->where('status', 'issued')->count();
        return $this->total_quantity - $issuedCopies;
    }

    public function getIssuedCopiesAttribute(): int
    {
        return $this->bookIssues()->where('status', 'issued')->count();
    }
}
