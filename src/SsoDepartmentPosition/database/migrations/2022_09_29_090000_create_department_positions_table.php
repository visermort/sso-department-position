<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateDepartmentPositionsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        $tableName = $this->getTableName();

        Schema::create($tableName, function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('position_id')->index()->comment('Id штатной должноси');
            $table->string('position_name')->nullable()->comment('Название должности');
            $table->uuid('department_id')->comment('Id департамента');
            $table->jsonb('departments')->comment('Массив родительских департаментов');
            $table->uuid('personnel_number_id')->nullable()->comment('Id персонального номера');
            $table->string('personnel_number')->nullable()->comment('Персональный номер');
            $table->string('first_name')->nullable()->comment('Имя');
            $table->string('last_name')->nullable()->comment('Фамилия');
            $table->string('patronymic')->nullable()->nullable()->comment('Отчество');
            $table->string('email')->nullable()->comment('Email');
            $table->string('tin')->nullable()->comment('ИНН');
            $table->string('department_name')->nullable()->comment('Название департамента');
            //$table->integer('test_assignment_status')->nullable()->comment('Статус назначенного тестирования');
            //$table->timestamp('test_assignment_date')->nullable()->comment('Дата назначенного тестирования');
            $table->uuid('position_functional_direction_id')->nullable()
                ->comment('Id функционального направления должности');
            $table->uuid('manager_functional_direction_id')->nullable()
                ->comment('Id функционального направления руководителя департамента');
            $table->integer('subordinates_count')->nullable()->comment('Количество подчинённых');
            $table->integer('goals_weight_id')->default(13)->index()->comment('Уровень должности');
            $table->boolean('is_mse')->default(false)->index();
            $table->integer('subordinates_with_employment_percent_count')->nullable()
                ->comment('Количество подчинённых у которых занятость больше 0');
            $table->string('position_sap_id')->nullable()->comment('SAP ID штатной должности');
            $table->uuid('manager_id')->nullable()->comment('Id должности руководителя');
            $table->integer('employee_percent')->nullable()->comment('Процент занятия должности');
            $table->boolean('is_acting')->nullable()->comment('Исполняет должность');
            $table->string('avatar_url')->nullable()->comment('Url адрес изображения');
            $table->string('level')->nullable()->comment('Разряд');
            $table->timestamps();
        });

        Schema::table($tableName, function (Blueprint $table) {
            $table->index('position_functional_direction_id');
            $table->index('position_id');
            $table->index('is_mse');
            $table->index('goals_weight_id');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        $tableName = $this->getTableName();

        Schema::dropIfExists($tableName);
    }

    protected function getTableName(): string
    {
        $tableName = config('sso_department_position.table_name');

        if (empty($tableName)) {
            throw new Exception('Empty config "sso_department_position.table_name"');
        }

        return $tableName;
    }
}
