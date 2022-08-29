<?php

namespace Erg\SsoDepartmentPosition\App\Console\Commands;

use Erg\SsoDepartmentPosition\App\Console\Utils\Logger;
use Erg\SsoDepartmentPosition\App\Models\DepartmentPosition;
use App\Repository\DepartmentPosition\DepartmentPositionRepository;
use App\Utils\Sso\Sso;
use Erg\Client\Sso\Entities\Department;
use Erg\Client\Sso\Entities\PersonnelNumberWithPivot;
use Erg\Client\Sso\Entities\Position;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Ramsey\Uuid\Uuid;
use App\App\Console\Commands\Utils\FunctioalDirections;

class LoadDepartmentPositions extends Command
{
    protected DepartmentPositionRepository $departmentPositionRepository;
    protected PersonalPlanRepository $personalPlanRepository;
    protected YearRepository $yearRepository;

    protected Sso $ssoUtils;

    protected int $loop = 0;

    protected Logger $logger;

    protected ?array $functionalDirections = null;

    private bool $verbose;

    private bool $deleteTable;

    private array $writeData = [];

    private ?string $tableName;

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'load:sso-department-positions {verbose?} {delete_table?}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Создание временной таблицы пользователей с их департаментами';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
        $this->ssoUtils = new Sso();
        $this->logger = new Logger($this, 'load_sso_department_positions');

        $this->tableName = config('sso_department_position.table_name');
        if (empty($this->tableName)) {
            throw new Exception('Empty config "sso_department_position.table_name"');
        }

    }

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $this->functionalDirections = $this->getFunctionalDirections();
        $verbose = $this->argument('verbose');
        $deleteTable = $this->argument('delete_table');
        $this->verbose = !!$verbose;
        $this->deleteTable = !!$deleteTable;
        return $this->loadDepartmentPositions();
    }

    protected function getFunctionalDirections(): ?array
    {
        return FunctioalDirections::getFunctionalDirections();
    }

    protected function getFunctionalDirection(?string $familyId): ?string
    {
        if (!$familyId) {
            return null;
        }

        return $this->functionalDirections[$familyId] ?? null;
    }


    protected function loadDepartmentPositions(): bool
    {
        $this->logger->log('Start');
        $departmentPositionTableName = (new DepartmentPosition)->getTable();
        $newDepartmentPositionTableName = 'new_' . $departmentPositionTableName;
        if (Schema::hasTable($newDepartmentPositionTableName) && $this->deleteTable) {
            Schema::dropIfExists($newDepartmentPositionTableName);
        }

        if (Schema::hasTable($newDepartmentPositionTableName)) {
            $msg = 'The script is already running at the moment';
            $this->alert($msg);
            $this->logger->log($msg);
            return false;
        }

        $parentDepartment = $this->getParentDepartment();
        if (!$parentDepartment) {
            $this->logger->log('Parent department not found');
            return false;
        }

        DB::statement("CREATE TABLE $newDepartmentPositionTableName (LIKE $departmentPositionTableName INCLUDING ALL);");

        try {
            $this->handleDepartment([], $parentDepartment, $newDepartmentPositionTableName);
            $this->writeDataInTable($newDepartmentPositionTableName);
        } catch (\Exception $e) {
            Schema::dropIfExists($newDepartmentPositionTableName);
            $this->logger->log('An error has occurred for more information, run the script with the verbose flag. Message: ' . $e->getMessage(), false);
            return false;
        }

        Schema::dropIfExists($departmentPositionTableName);
        Schema::rename($newDepartmentPositionTableName, $departmentPositionTableName);

        $this->syncCountSubordinates();

        $currentYear = now()->year;
        $years = $this->yearRepository->findByIds([$currentYear, $currentYear + 1]);
        $yearIds = $years->pluck('id')->toArray();
        $this->personalPlanRepository->createAllUsers($yearIds);

        $this->logger->log('Done');
        return true;
    }

    protected function handleDepartment(array $grandParents, Department $parentDepartment, string $tableName): bool
    {
        if (count($this->writeData) >= 1000) {
            $this->writeDataInTable($tableName);
        }

        $this->loop++;

        $positions = $this->getDepartmentPositions($parentDepartment->id);

        try {
            $manager = $parentDepartment->manager_id ? $this->ssoUtils->getPosition($parentDepartment->manager_id) : null;
        } catch (\Exception $e) {
            $manager = null;
        }

        $grandParents[] = $parentDepartment->id;
        if (!empty($positions)) {
            $this->addedDataForWriteTable(
                $grandParents,
                $parentDepartment,
                $positions,
                $manager && $manager->family_id ? $this->getFunctionalDirection($manager->family_id) : null
            );
        }

        $countPositions = $positions ? count($positions) : 0;
        unset($positions);
        unset($manager);

        $childDepartments = $this->getChildDepartments($parentDepartment->id);
        $this->logger->log($this->loop . '.' . $parentDepartment->id .
            ', children ' . ($childDepartments ? count($childDepartments) : 0) .
            ', positions ' . $countPositions);

        if (!empty($childDepartments)) {
            foreach ($childDepartments as $idx => $department) {
                $this->handleDepartment($grandParents, $department, $tableName);
                unset($childDepartments[$idx]);
            }
        }

        return true;
    }

    private function writeDataInTable(string $tableName)
    {
        DB::table($tableName)->insert($this->writeData);
        $this->writeData = [];
    }

    protected function addedDataForWriteTable(
        array $grandParents,
        Department $parentDepartment,
        array $positions,
        ?string $managerFunctionalDirectionId
    ): void {
        foreach ($positions as $position) {
            if ($this->verbose) {
                $this->logger->log('Записываем данные по позиции с ид =  ' . $position->id);
            }

            /* @var $position Position */
            if (empty($position->personnel_numbers)) {
                if ($this->verbose) {
                    $this->logger->log('К позиции не привязаны сотрудники');
                }

                $params = $this->getParams($position, $parentDepartment, $grandParents, $managerFunctionalDirectionId);
                $this->writeData[] = $params;
                continue;
            }

            foreach ($position->personnel_numbers as $personnelNumber) {
                if ($this->verbose) {
                    $this->logger->log('Обрабатываем сотрудника с ид = ' . $personnelNumber->id);
                }

                $params = $this->getParams($position, $parentDepartment, $grandParents, $managerFunctionalDirectionId,
                    $personnelNumber);
                $this->writeData[] = $params;
            }
        }
    }

    private function getParams(
        Position $position,
        Department $parentDepartment,
        array $grandParents,
        ?string $managerFunctionalDirectionId,
        ?PersonnelNumberWithPivot $personnelNumber = null
    ): array {
        return [
            'id' => Uuid::uuid4()->toString(),
            'personnel_number_id' => $personnelNumber ? $personnelNumber->id : null,
            'personnel_number' => $personnelNumber ? $personnelNumber->personnel_number : null,
            'first_name' => $personnelNumber ? $personnelNumber->first_name : null,
            'last_name' => $personnelNumber ? $personnelNumber->last_name : null,
            'patronymic' => $personnelNumber ? $personnelNumber->patronymic : null,
            'email' => $personnelNumber ? $personnelNumber->email : null,
            'tin' => $personnelNumber ? $personnelNumber->tin : null,
            'position_id' => $position->id,
            'position_name' => $position->name,
            'department_id' => $parentDepartment->id,
            'departments' => json_encode($grandParents),
            'department_name' => $parentDepartment->name,
            'position_functional_direction_id' => $position->family_id ? $this->getFunctionalDirection($position->family_id) : null,
            'manager_functional_direction_id' => $managerFunctionalDirectionId,
            'subordinates_count' => $position->subordinates_count,
            'goals_weight_id' => $position->goals_weight_id ?? 13,
            'is_mse' => $position->is_mse,
            'position_sap_id' => $position->sap_id,
            'manager_id' => $position->manager_id,
            'employee_percent' => $personnelNumber ? $personnelNumber->employment_percent : null,
            'is_acting' => $personnelNumber ? $personnelNumber->is_acting : null,
            'avatar_url' => $personnelNumber ? $personnelNumber->avatar_url : null,
            'level' => $position->level
        ];
    }

    protected function getDepartmentPositions(string $departmentId): ?array
    {
        try {
            return $this->ssoUtils->getDepartmentPositions([$departmentId]);
        } catch (\Exception $e) {
            $this->logger->log('Error getting positions for department ' . $departmentId . ' ' . $e->getMessage(),
                false);
        }
        return null;
    }

    protected function getParentDepartment(): ?Department
    {
        try {
            return $this->ssoUtils->getTopDepartmentsInternal(false);
        } catch (\Exception $e) {
            $this->logger->log('Error getting top department ' . $e->getMessage(), false);
        }
        return null;
    }

    protected function getChildDepartments(string $departmentId): ?array
    {
        try {
            return $this->ssoUtils->getChildDepartments($departmentId);
        } catch (\Exception $e) {
            $this->logger->log('Error getting child departments for ' . $departmentId . ' ' . $e->getMessage(), false);
        }
        return null;
    }

    public function syncCountSubordinates(): void
    {
        DepartmentPosition::query()
            ->select('edu_department_position.*')
            ->selectRaw('count(subordinates.personnel_number_id) as subordinates_with_employment_percent_count')
            ->join('edu_department_position as subordinates', 'subordinates.manager_id', '=',
                'edu_department_position.position_id')
            ->whereNotNull('subordinates.personnel_number_id')
            ->where(function ($q) {
                return $q->where('subordinates.employee_percent', '>', 0)
                    ->orWhereNull('subordinates.employee_percent');
            })
            ->where('subordinates.is_acting', false)
            ->groupBy(['edu_department_position.id'])
            ->get()
            ->chunk(1000)
            ->each(function ($chunk) {
                DepartmentPosition::upsert($chunk->toArray(), 'id');
            });
    }
}
