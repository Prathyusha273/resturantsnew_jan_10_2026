<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddDatabaseIndexesForPerformance extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        // Add indexes to users table for better performance
        Schema::table('restaurant_users', function (Blueprint $table) {
            $table->index('firebase_uid', 'idx_users_firebase_uid');
            $table->index('status', 'idx_users_status');
            $table->index(['status', 'firebase_uid'], 'idx_users_status_firebase_uid');
            $table->index('created_at', 'idx_users_created_at');
        });

        // Add indexes to vendor_users table
        Schema::table('restaurant_vendor_users', function (Blueprint $table) {
            $table->index('user_id', 'idx_vendor_users_user_id');
            $table->index('uuid', 'idx_vendor_users_uuid');
        });

        // Add indexes to model_has_roles table
        Schema::table('model_has_roles', function (Blueprint $table) {
            $table->index('model_id', 'idx_model_has_roles_model_id');
            $table->index('role_id', 'idx_model_has_roles_role_id');
            $table->index(['model_id', 'role_id'], 'idx_model_has_roles_model_role');
        });

        // Add indexes to model_has_permissions table
        Schema::table('model_has_permissions', function (Blueprint $table) {
            $table->index('model_id', 'idx_model_has_permissions_model_id');
            $table->index('permission_id', 'idx_model_has_permissions_permission_id');
            $table->index(['model_id', 'permission_id'], 'idx_model_has_permissions_model_permission');
        });

        // Add indexes to security_audit_logs table
        Schema::table('security_audit_logs', function (Blueprint $table) {
            $table->index('user_id', 'idx_security_audit_logs_user_id');
            $table->index('ip_address', 'idx_security_audit_logs_ip_address');
            $table->index('created_at', 'idx_security_audit_logs_created_at');
            $table->index(['user_id', 'created_at'], 'idx_security_audit_logs_user_created');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex('idx_users_firebase_uid');
            $table->dropIndex('idx_users_status');
            $table->dropIndex('idx_users_status_firebase_uid');
            $table->dropIndex('idx_users_created_at');
        });

        Schema::table('vendor_users', function (Blueprint $table) {
            $table->dropIndex('idx_vendor_users_user_id');
            $table->dropIndex('idx_vendor_users_uuid');
        });

        Schema::table('model_has_roles', function (Blueprint $table) {
            $table->dropIndex('idx_model_has_roles_model_id');
            $table->dropIndex('idx_model_has_roles_role_id');
            $table->dropIndex('idx_model_has_roles_model_role');
        });

        Schema::table('model_has_permissions', function (Blueprint $table) {
            $table->dropIndex('idx_model_has_permissions_model_id');
            $table->dropIndex('idx_model_has_permissions_permission_id');
            $table->dropIndex('idx_model_has_permissions_model_permission');
        });

        Schema::table('security_audit_logs', function (Blueprint $table) {
            $table->dropIndex('idx_security_audit_logs_user_id');
            $table->dropIndex('idx_security_audit_logs_ip_address');
            $table->dropIndex('idx_security_audit_logs_created_at');
            $table->dropIndex('idx_security_audit_logs_user_created');
        });
    }
}
