<?php

namespace Erg\SsoDepartmentPosition\App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Class DepartmentPosition
 * @package App\Models
 *
 * @property string $id
 * @property string $personnel_number_id Id Персонального номера
 * @property string $personnel_number Персональный номер
 * @property string $first_name Имя'
 * @property string $last_name Фамилия
 * @property string $patronymic Отчество
 * @property string $email Email
 * @property string $tin ИНН
 * @property string $position_id Id штатной должноси
 * @property string $position_name Название должности
 * @property string $department_id Id департамента
 * @property string $departments Массив родительских департаментов
 * @property string $department_name Название департамента
 * @property integer $test_assignment_status Статус назначенного тестирования
 * @property string $test_assignment_date Дата назначенного тестирования
 * @property string $position_functional_direction_id Функциональне направление должности
 * @property string $manager_functional_direction_id Функциональное направления руководителя
 * @property integer|null $subordinates_count Количество подчинённых
 * @property integer|null $subordinates_with_employment_percent_count Количество активных подчинённых
 * @property boolean $is_mse Является РСС
 * @property integer $goals_weight_id Уровень должности
 * @property string $position_sap_id SAP ID штатной должности
 * @property string $manager_id Id должности руководителя
 * @property integer $employee_percent Процент занятия должности
 * @property bool $is_acting Исполняет должность
 * @property string|null $avatar_url Url адрес изображения
 * @property string|null $level Разряд
 * @property string $created_at Когда создано
 * @property string $updated_at Когда изменено
 *
 */
class DepartmentPosition extends Model
{
    use HasFactory;

    protected $keyType = 'string';

    const TEST_ASSIGNMENT_STATUS_CREATE = 10;
    const TEST_ASSIGNMENT_STATUS_IN_TESTING = 20;
    const TEST_ASSIGNMENT_STATUS_SUCCESS = 30;
    const TEST_ASSIGNMENT_STATUS_SKIP = 40;
    const TEST_ASSIGNMENT_STATUS_SENT_RESULT = 50;
    const TEST_ASSIGNMENT_STATUS_APPROVE = 60;

    const TEST_ASSIGNMENT_STATUSES = [
        self::TEST_ASSIGNMENT_STATUS_CREATE,
        self::TEST_ASSIGNMENT_STATUS_IN_TESTING,
        self::TEST_ASSIGNMENT_STATUS_SUCCESS,
        self::TEST_ASSIGNMENT_STATUS_SKIP,
        self::TEST_ASSIGNMENT_STATUS_SENT_RESULT,
        self::TEST_ASSIGNMENT_STATUS_APPROVE,
    ];

    public function __construct(array $attributes = [])
    {
        parent::__construct($attributes);

        // todo error if empty

        $this->table = config('sso_department_position.table_name');
    }

    public function scopeOnlyActive(Builder $query): Builder
    {
        return $query->where(function (Builder $q) {
            return $q->where('employee_percent', '>', 0)
                ->orWhereNull('employee_percent');
        });
    }

    public function scopeOnlyPersonnelNumber(Builder $query): Builder
    {
        return $query->whereNotNull('personnel_number_id');
    }

    public function scopeOnlyNonActing(Builder $query): Builder
    {
        return $query->where(function (Builder $q) {
            return $q->where('is_acting', false)
                ->orWhereNull('is_acting');
        });
    }
}
