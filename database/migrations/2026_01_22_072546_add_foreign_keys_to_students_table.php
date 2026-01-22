<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('students', function (Blueprint $table) {
            // Add foreign key columns if they don't exist
            if (!Schema::hasColumn('students', 'guardian_id')) {
                $table->unsignedBigInteger('guardian_id')->nullable()->after('user_id');
            }
            
            if (!Schema::hasColumn('students', 'class_id')) {
                $table->unsignedBigInteger('class_id')->nullable()->after('class');
            }
            
            if (!Schema::hasColumn('students', 'section_id')) {
                $table->unsignedBigInteger('section_id')->nullable()->after('class_id');
            }
            
            // Add indexes for performance (check if they don't exist)
            if (!Schema::hasIndex('students', 'students_user_id_index')) {
                $table->index('user_id');
            }
            if (!Schema::hasIndex('students', 'students_guardian_id_index')) {
                $table->index('guardian_id');
            }
            if (!Schema::hasIndex('students', 'students_class_id_index')) {
                $table->index('class_id');
            }
            if (!Schema::hasIndex('students', 'students_section_id_index')) {
                $table->index('section_id');
            }
            
            // Add foreign key constraints (skip if already exist)
            try {
                if (!Schema::hasTable('users') || !Schema::hasColumn('students', 'user_id')) {
                    // Skip if either table or column doesn't exist
                } else {
                    // Check if foreign key constraint already exists
                    $connection = DB::getPdo();
                    $result = $connection->query("
                        SELECT CONSTRAINT_NAME 
                        FROM information_schema.KEY_COLUMN_USAGE 
                        WHERE TABLE_SCHEMA = DATABASE() 
                        AND TABLE_NAME = 'students' 
                        AND COLUMN_NAME = 'user_id'
                        AND REFERENCED_TABLE_NAME = 'users'
                        AND REFERENCED_COLUMN_NAME = 'id'
                    ");
                    
                    if ($result->rowCount() == 0) {
                        $table->foreign('user_id')->references('id')->on('users')->onDelete('set null');
                    }
                }
            } catch (\Exception $e) {
                // Foreign key already exists, continue
            }
            
            try {
                if (!Schema::hasTable('guardians') || !Schema::hasColumn('students', 'guardian_id')) {
                    // Skip if either table or column doesn't exist
                } else {
                    // Check if foreign key constraint already exists
                    $connection = DB::getPdo();
                    $result = $connection->query("
                        SELECT CONSTRAINT_NAME 
                        FROM information_schema.KEY_COLUMN_USAGE 
                        WHERE TABLE_SCHEMA = DATABASE() 
                        AND TABLE_NAME = 'students' 
                        AND COLUMN_NAME = 'guardian_id'
                        AND REFERENCED_TABLE_NAME = 'guardians'
                        AND REFERENCED_COLUMN_NAME = 'id'
                    ");
                    
                    if ($result->rowCount() == 0) {
                        $table->foreign('guardian_id')->references('id')->on('guardians')->onDelete('set null');
                    }
                }
            } catch (\Exception $e) {
                // Foreign key already exists, continue
            }
            
            try {
                if (!Schema::hasTable('class_managements') || !Schema::hasColumn('students', 'class_id')) {
                    // Skip if either table or column doesn't exist
                } else {
                    // Check if foreign key constraint already exists
                    $connection = DB::getPdo();
                    $result = $connection->query("
                        SELECT CONSTRAINT_NAME 
                        FROM information_schema.KEY_COLUMN_USAGE 
                        WHERE TABLE_SCHEMA = DATABASE() 
                        AND TABLE_NAME = 'students' 
                        AND COLUMN_NAME = 'class_id'
                        AND REFERENCED_TABLE_NAME = 'class_managements'
                        AND REFERENCED_COLUMN_NAME = 'id'
                    ");
                    
                    if ($result->rowCount() == 0) {
                        $table->foreign('class_id')->references('id')->on('class_managements')->onDelete('set null');
                    }
                }
            } catch (\Exception $e) {
                // Foreign key already exists, continue
            }
            
            try {
                if (!Schema::hasTable('sections') || !Schema::hasColumn('students', 'section_id')) {
                    // Skip if either table or column doesn't exist
                } else {
                    // Check if foreign key constraint already exists
                    $connection = DB::getPdo();
                    $result = $connection->query("
                        SELECT CONSTRAINT_NAME 
                        FROM information_schema.KEY_COLUMN_USAGE 
                        WHERE TABLE_SCHEMA = DATABASE() 
                        AND TABLE_NAME = 'students' 
                        AND COLUMN_NAME = 'section_id'
                        AND REFERENCED_TABLE_NAME = 'sections'
                        AND REFERENCED_COLUMN_NAME = 'id'
                    ");
                    
                    if ($result->rowCount() == 0) {
                        $table->foreign('section_id')->references('id')->on('sections')->onDelete('set null');
                    }
                }
            } catch (\Exception $e) {
                // Foreign key already exists, continue
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('students', function (Blueprint $table) {
            // Drop foreign key constraints first
            try {
                $connection = DB::getPdo();
                $result = $connection->query("
                    SELECT CONSTRAINT_NAME 
                    FROM information_schema.KEY_COLUMN_USAGE 
                    WHERE TABLE_SCHEMA = DATABASE() 
                    AND TABLE_NAME = 'students' 
                    AND COLUMN_NAME = 'user_id'
                    AND REFERENCED_TABLE_NAME = 'users'
                    AND REFERENCED_COLUMN_NAME = 'id'
                ");
                
                if ($result->rowCount() > 0) {
                    $constraint = $result->fetch();
                    $table->dropForeign([$constraint['CONSTRAINT_NAME']]);
                }
            } catch (\Exception $e) {
                // Ignore if constraint doesn't exist
            }
            
            try {
                $connection = DB::getPdo();
                $result = $connection->query("
                    SELECT CONSTRAINT_NAME 
                    FROM information_schema.KEY_COLUMN_USAGE 
                    WHERE TABLE_SCHEMA = DATABASE() 
                    AND TABLE_NAME = 'students' 
                    AND COLUMN_NAME = 'guardian_id'
                    AND REFERENCED_TABLE_NAME = 'guardians'
                    AND REFERENCED_COLUMN_NAME = 'id'
                ");
                
                if ($result->rowCount() > 0) {
                    $constraint = $result->fetch();
                    $table->dropForeign([$constraint['CONSTRAINT_NAME']]);
                }
            } catch (\Exception $e) {
                // Ignore if constraint doesn't exist
            }
            
            try {
                $connection = DB::getPdo();
                $result = $connection->query("
                    SELECT CONSTRAINT_NAME 
                    FROM information_schema.KEY_COLUMN_USAGE 
                    WHERE TABLE_SCHEMA = DATABASE() 
                    AND TABLE_NAME = 'students' 
                    AND COLUMN_NAME = 'class_id'
                    AND REFERENCED_TABLE_NAME = 'class_managements'
                    AND REFERENCED_COLUMN_NAME = 'id'
                ");
                
                if ($result->rowCount() > 0) {
                    $constraint = $result->fetch();
                    $table->dropForeign([$constraint['CONSTRAINT_NAME']]);
                }
            } catch (\Exception $e) {
                // Ignore if constraint doesn't exist
            }
            
            try {
                $connection = DB::getPdo();
                $result = $connection->query("
                    SELECT CONSTRAINT_NAME 
                    FROM information_schema.KEY_COLUMN_USAGE 
                    WHERE TABLE_SCHEMA = DATABASE() 
                    AND TABLE_NAME = 'students' 
                    AND COLUMN_NAME = 'section_id'
                    AND REFERENCED_TABLE_NAME = 'sections'
                    AND REFERENCED_COLUMN_NAME = 'id'
                ");
                
                if ($result->rowCount() > 0) {
                    $constraint = $result->fetch();
                    $table->dropForeign([$constraint['CONSTRAINT_NAME']]);
                }
            } catch (\Exception $e) {
                // Ignore if constraint doesn't exist
            }
            
            // Drop columns (except user_id which is handled by other migration)
            $columnsToDrop = [];
            if (Schema::hasColumn('students', 'guardian_id')) {
                $columnsToDrop[] = 'guardian_id';
            }
            if (Schema::hasColumn('students', 'class_id')) {
                $columnsToDrop[] = 'class_id';
            }
            if (Schema::hasColumn('students', 'section_id')) {
                $columnsToDrop[] = 'section_id';
            }
            
            if (!empty($columnsToDrop)) {
                $table->dropColumn($columnsToDrop);
            }
        });
    }
};