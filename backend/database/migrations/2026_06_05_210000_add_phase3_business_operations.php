<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('legal_matters', function (Blueprint $table) {
            $table->string('billing_type')->default('hourly')->after('description');
            $table->decimal('billing_rate', 12, 2)->nullable()->after('billing_type');
            $table->decimal('fixed_fee_amount', 12, 2)->nullable()->after('billing_rate');
            $table->decimal('retainer_minimum_amount', 12, 2)->nullable()->after('fixed_fee_amount');
        });

        Schema::create('case_expenses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('legal_matter_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('invoice_id')->nullable()->constrained()->nullOnDelete();
            $table->string('category')->nullable();
            $table->text('description');
            $table->decimal('amount', 12, 2);
            $table->date('expense_date');
            $table->boolean('billable')->default(true);
            $table->string('status')->default('approved');
            $table->timestamps();
            $table->softDeletes();

            $table->index(['organization_id', 'legal_matter_id']);
            $table->index(['invoice_id']);
        });

        Schema::table('invoice_line_items', function (Blueprint $table) {
            $table->foreignId('case_expense_id')->nullable()->after('time_entry_id')->constrained()->nullOnDelete();
        });

        Schema::table('intake_submissions', function (Blueprint $table) {
            $table->foreignId('client_id')->nullable()->after('intake_form_id')->constrained()->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('intake_submissions', function (Blueprint $table) {
            $table->dropConstrainedForeignId('client_id');
        });

        Schema::table('invoice_line_items', function (Blueprint $table) {
            $table->dropConstrainedForeignId('case_expense_id');
        });

        Schema::dropIfExists('case_expenses');

        Schema::table('legal_matters', function (Blueprint $table) {
            $table->dropColumn(['billing_type', 'billing_rate', 'fixed_fee_amount', 'retainer_minimum_amount']);
        });
    }
};
