<?php

namespace App\Imports;

use App\Models\exam_schedules;
use Maatwebsite\Excel\Concerns\ToModel;
use Maatwebsite\Excel\Concerns\WithHeadingRow;

class ExamSchedulesImport implements ToModel, WithHeadingRow
{
    protected $passingScore = 75; // You can adjust this

    public function model(array $row)
    {
        // Only update if ID and exam_score are present
        if (isset($row['id']) && isset($row['exam_score'])) {
            $examSchedule = exam_schedules::find($row['id']);
            if ($examSchedule) {
                $score = $row['exam_score'];

                // Update score
                $examSchedule->exam_score = $score;

                // Auto update exam_status
                $examSchedule->exam_status = ($score >= $this->passingScore) ? 'passed' : 'reconsider';

                // Optional: reset email_sent if you want to resend for newly passed
                if ($examSchedule->exam_status === 'passed' && !$examSchedule->exam_score_sent) {
                    // You can trigger email here if needed
                    // Mail::send(...);
                    $examSchedule->exam_score_sent = 1; // mark as sent
                }

                $examSchedule->save();
            }
        }

        return null; // Don't create new records
    }
}
