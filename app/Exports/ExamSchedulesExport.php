<?php

namespace App\Exports;

use App\Models\exam_schedules;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;

class ExamSchedulesExport implements FromCollection, WithHeadings
{
    public function collection()
    {
        return exam_schedules::with('admission')
            ->get()
            ->map(function ($exam) {
                return [
                    'ID'             => $exam->id, // added for import reference
                    'First Name'     => $exam->admission->first_name ?? '',
                    'Last Name'      => $exam->admission->last_name ?? '',
                    'Email'          => $exam->admission->email ?? '',
                    'Academic Year'  => $exam->admission->academic_year_id ?? '',
                    'Test Permit No' => $exam->test_permit_no,
                    'Exam Date'      => $exam->exam_date,
                    'Exam Time From' => $exam->exam_time_from,
                    'Exam Time To'   => $exam->exam_time_to,
                    'Campus'         => $exam->campus_id,
                    'Room'           => $exam->room_id,
                    'Building'       => $exam->building_id,
                    'Exam Score'     => $exam->exam_score,
                ];
            });
    }

    public function headings(): array
    {
        return [
            'ID', // added
            'First Name',
            'Last Name',
            'Email',
            'Academic Year',
            'Test Permit No',
            'Exam Date',
            'Exam Time From',
            'Exam Time To',
            'Campus',
            'Room',
            'Building',
            'Exam Score',
        ];
    }
}
