<?php

namespace App\Exports;

use App\Models\User;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

class InternsExport implements FromCollection, WithHeadings, WithMapping
{
    protected $ids;
    protected $type;

    public function __construct(array $ids, string $type)
    {
        $this->ids = $ids;
        $this->type = $type;
    }

    public function collection()
    {
        // We ensure the relationships are eager-loaded.
        return User::withTrashed()
            ->with(['intern.department', 'intern.school'])
            ->whereIn('id', $this->ids)
            ->get();
    }

    public function headings(): array
    {
        return [
            'ID',
            'First Name',
            'Last Name',
            'Email',
            'Department',
            'School',
            'Status'
        ];
    }

    public function map($user): array
    {
        // 1. Initialize fallbacks
        $departmentName = 'N/A';
        $schoolName = 'N/A';

        // 2. Explicitly check if the 'intern' relationship exists
        if ($user->intern) {
            
            // 3. Check for the department
            if ($user->intern->department) {
                $departmentName = $user->intern->department->name;
            } elseif ($user->assigned_department) {
                $departmentName = $user->assigned_department;
            }

            // 4. ✨ EXPLICIT SCHOOL CHECK ✨
            // We check if the relationship exists AND if the 'name' property is accessible.
            if ($user->intern->school && !empty($user->intern->school->name)) {
                $schoolName = $user->intern->school->name;
            }
        }

        return [
            $user->id,
            $user->first_name,
            $user->last_name,
            $user->email,
            $departmentName,
            $schoolName,
            $user->status
        ];
    }
}