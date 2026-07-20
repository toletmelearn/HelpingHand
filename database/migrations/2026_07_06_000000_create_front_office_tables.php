<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // 1. Alter existing admission_enquiries table
        Schema::table('admission_enquiries', function (Blueprint $table) {
            $table->unsignedBigInteger('counsellor_id')->nullable()->after('status');
            $table->string('aadhaar_number')->nullable()->after('phone');
            $table->dateTime('visit_scheduled_at')->nullable()->after('counsellor_id');
            $table->date('follow_up_date')->nullable()->after('visit_scheduled_at');
            $table->text('follow_up_notes')->nullable()->after('follow_up_date');
            $table->text('remarks')->nullable()->after('follow_up_notes');

            $table->foreign('counsellor_id')->references('id')->on('users')->onDelete('set null');
        });

        // 2. Alter existing gate_entries table (visitor entries)
        Schema::table('gate_entries', function (Blueprint $table) {
            $table->string('phone')->nullable()->after('visitor_name');
            $table->string('department')->nullable()->after('purpose');
            $table->string('id_proof_type')->nullable()->after('department');
            $table->string('id_proof_number')->nullable()->after('id_proof_type');
            $table->string('photo_path')->nullable()->after('id_proof_number');
            $table->boolean('is_blacklisted')->default(false)->after('photo_path');
            $table->boolean('is_emergency')->default(false)->after('is_blacklisted');
            $table->text('remarks')->nullable()->after('is_emergency');
        });

        // 3. Create appointments table
        Schema::create('appointments', function (Blueprint $table) {
            $table->id();
            $table->string('visitor_name');
            $table->unsignedBigInteger('guardian_id')->nullable();
            $table->unsignedBigInteger('teacher_id')->nullable();
            $table->unsignedBigInteger('receptionist_id')->nullable();
            $table->date('scheduled_date');
            $table->time('start_time');
            $table->time('end_time');
            $table->string('purpose');
            $table->string('status')->default('pending'); // pending, approved, rejected, completed, no_show
            $table->text('feedback')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('guardian_id')->references('id')->on('guardians')->onDelete('set null');
            $table->foreign('teacher_id')->references('id')->on('users')->onDelete('set null');
            $table->foreign('receptionist_id')->references('id')->on('users')->onDelete('set null');

            $table->index(['scheduled_date', 'start_time', 'end_time']);
            $table->index(['teacher_id', 'scheduled_date']);
        });

        // 4. Create call_logs table
        Schema::create('call_logs', function (Blueprint $table) {
            $table->id();
            $table->string('caller_name');
            $table->string('phone');
            $table->string('call_type'); // incoming, outgoing
            $table->string('purpose'); // admission, fee_reminder, emergency, general
            $table->integer('duration')->default(0); // in seconds
            $table->unsignedBigInteger('assigned_user_id')->nullable();
            $table->string('status')->default('completed'); // completed, missed, follow_up_required
            $table->date('follow_up_date')->nullable();
            $table->text('outcome')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('assigned_user_id')->references('id')->on('users')->onDelete('set null');

            $table->index(['phone']);
            $table->index(['follow_up_date']);
        });

        // 5. Create couriers table
        Schema::create('couriers', function (Blueprint $table) {
            $table->id();
            $table->string('tracking_number');
            $table->string('courier_company');
            $table->string('courier_type'); // incoming, outgoing
            $table->string('parcel_type'); // document, package, registered_post, speed_post, regular
            $table->string('sender');
            $table->string('receiver');
            $table->unsignedBigInteger('recipient_user_id')->nullable();
            $table->date('delivery_date')->nullable();
            $table->string('status')->default('pending'); // pending, delivered, returned, lost
            $table->string('attachment_path')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('recipient_user_id')->references('id')->on('users')->onDelete('set null');

            $table->index(['tracking_number']);
            $table->index(['recipient_user_id']);
        });

        // 6. Create lost_found_items table
        Schema::create('lost_found_items', function (Blueprint $table) {
            $table->id();
            $table->string('item_name');
            $table->text('description')->nullable();
            $table->string('location_found');
            $table->string('location_lost')->nullable();
            $table->string('item_type'); // lost, found
            $table->date('date_reported');
            $table->unsignedBigInteger('reported_by_user_id')->nullable();
            $table->string('reported_by_name')->nullable();
            $table->string('claimant_name')->nullable();
            $table->string('claimant_phone')->nullable();
            $table->text('verification_details')->nullable();
            $table->string('status')->default('lost'); // lost, found, claimed, returned
            $table->timestamp('returned_at')->nullable();
            $table->string('photo_path')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('reported_by_user_id')->references('id')->on('users')->onDelete('set null');
        });

        // 7. Create gate_passes table
        Schema::create('gate_passes', function (Blueprint $table) {
            $table->id();
            $table->string('pass_type'); // student, staff, visitor, vehicle
            $table->string('holder_name');
            $table->unsignedBigInteger('student_id')->nullable();
            $table->unsignedBigInteger('user_id')->nullable(); // staff user reference
            $table->string('vehicle_no')->nullable();
            $table->string('purpose');
            $table->date('request_date');
            $table->time('departure_time');
            $table->time('arrival_time')->nullable();
            $table->unsignedBigInteger('requested_by')->nullable();
            $table->unsignedBigInteger('approved_by')->nullable();
            $table->unsignedBigInteger('verified_by')->nullable();
            $table->string('status')->default('pending'); // pending, approved, rejected, active, completed
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('student_id')->references('id')->on('students')->onDelete('cascade');
            $table->foreign('user_id')->references('id')->on('users')->onDelete('set null');
            $table->foreign('requested_by')->references('id')->on('users')->onDelete('set null');
            $table->foreign('approved_by')->references('id')->on('users')->onDelete('set null');
            $table->foreign('verified_by')->references('id')->on('users')->onDelete('set null');

            $table->index(['pass_type', 'request_date']);
            $table->index(['student_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('gate_passes');
        Schema::dropIfExists('lost_found_items');
        Schema::dropIfExists('couriers');
        Schema::dropIfExists('call_logs');
        Schema::dropIfExists('appointments');

        Schema::table('gate_entries', function (Blueprint $table) {
            $table->dropColumn([
                'phone',
                'department',
                'id_proof_type',
                'id_proof_number',
                'photo_path',
                'is_blacklisted',
                'is_emergency',
                'remarks',
            ]);
        });

        Schema::table('admission_enquiries', function (Blueprint $table) {
            $table->dropForeign(['counsellor_id']);
            $table->dropColumn([
                'counsellor_id',
                'aadhaar_number',
                'visit_scheduled_at',
                'follow_up_date',
                'follow_up_notes',
                'remarks',
            ]);
        });
    }
};
