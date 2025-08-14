<?php

namespace App\Helpers;

use App\Models\AuditLog;
use Illuminate\Support\Facades\Auth;

class AuditLogger
{
    public static function log($actionPerformed, $statusBefore = 'N/A', $statusAfter = 'N/A')
    {
        // Get authenticated user
        $user = Auth::user();

        // Create an audit log entry
        AuditLog::create([
            'timestamp'        => now(),
            'full_name'        => $user->first_name . ' ' . $user->last_name,
            'email'            => $user->email,
            'role'             => $user->role_name,
            'action_performed' => $actionPerformed,
            'status_before'    => $statusBefore,
            'status_after'     => $statusAfter,
        ]);
    }
}
