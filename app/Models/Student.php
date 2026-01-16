<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Student extends Model
{
    use HasFactory;

    // Mass assignment se bachane ke liye
    protected $fillable = [
        'name',
        'father_name', 
        'mother_name',
        'date_of_birth',
        'aadhar_number',
        'address',
        'phone'
    ];

    // Aadhar validation rule (optional, baad mein use karenge)
    public static function rules()
    {
        return [
            'aadhar_number' => 'required|digits:12|unique:students'
        ];
    }
}