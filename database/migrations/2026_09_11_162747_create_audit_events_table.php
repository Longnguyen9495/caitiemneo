<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The company-wide, append-only record of who changed what.
 *
 * Deliberately not a polymorphic relation with a foreign key: the whole point
 * of this table is to outlive the row it describes. A financial record that is
 * deleted must leave its history behind, so the subject is stored as a plain
 * type/id pair plus a label snapshot, and nothing here cascades.
 *
 * Nothing in the application updates or deletes these rows; a correction is a
 * new event.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('audit_events', function (Blueprint $table): void {
            $table->id();

            // Subject of the event, stored loosely on purpose (see class docs).
            $table->string('auditable_type', 60);
            $table->unsignedBigInteger('auditable_id')->nullable();
            // Human-readable stamp of the subject at the time, so a deleted row
            // is still identifiable in the trail (invoice number, shift name…).
            $table->string('auditable_label')->nullable();

            $table->string('action', 60);

            $table->foreignId('branch_id')->nullable()->constrained()->nullOnDelete();
            // The actor is kept by id and by name: deactivating or removing an
            // account must never erase who performed the action.
            $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('actor_name')->nullable();

            $table->string('reason', 500)->nullable();
            $table->json('before')->nullable();
            $table->json('after')->nullable();

            // Ties an event to the request that produced it, so several changes
            // made in one submit can be read as one action.
            $table->uuid('correlation_id')->nullable();
            $table->string('ip_hash', 64)->nullable();
            $table->string('user_agent', 255)->nullable();

            $table->timestamps();

            $table->index(['auditable_type', 'auditable_id', 'id'], 'audit_events_subject_index');
            $table->index(['branch_id', 'created_at'], 'audit_events_branch_index');
            $table->index(['actor_id', 'created_at'], 'audit_events_actor_index');
            $table->index(['action', 'created_at'], 'audit_events_action_index');
            $table->index('correlation_id', 'audit_events_correlation_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('audit_events');
    }
};
