<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\admissions;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Facades\Auth;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Mail;
use App\Models\accounts;
use App\Models\AdmissionReservation;
use App\Models\building_rooms;
use App\Models\courses;
use App\Models\school_campus;
use App\Models\school_years;
use App\Models\exam_schedules;
use App\Models\campus_buildings;
use Illuminate\Validation\Rule;
use Illuminate\Support\Carbon;
use App\Models\students;
use Illuminate\Support\Facades\Storage;
use Throwable;
use Exception;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;



class AdmissionsController extends Controller
{


   public function getExamSchedules(Request $request)
{
    $perPage = (int) $request->input('per_page', 10);
    $page = (int) $request->input('page', 1);

    $query = exam_schedules::with(['applicant', 'campus', 'building', 'room']);

    // Search filter (by applicant or schedule fields)
    if ($search = $request->input('search')) {
        $query->whereHas('applicant', function ($q) use ($search) {
            $q->where('first_name', 'like', "%{$search}%")
              ->orWhere('last_name', 'like', "%{$search}%")
              ->orWhere('email', 'like', "%{$search}%");
        });
    }

    // Order by created_at (latest first)
    $paginated = $query->orderBy('created_at', 'desc')
        ->paginate($perPage, ['*'], 'page', $page);

    // Transform into flat table rows
    $examInfo = $paginated->getCollection()->map(function ($schedule) {
        return [
            'schedule_id' => $schedule->id,
            'campus_id' => $schedule->campus->id ?? null,
            'campus_name' => $schedule->campus->campus_name ?? null,
            'building_id' => $schedule->building->id ?? null,
            'building_name' => $schedule->building->building_name ?? null,
            'room_id' => $schedule->room->id ?? null,
            'room_name' => $schedule->room->room_name ?? null,
            'admission_id' => $schedule->applicant->admission_id ?? null,
            'test_permit_no' => $schedule->applicant->test_permit_no ?? null,
            'first_name' => $schedule->applicant->first_name ?? null,
            'last_name' => $schedule->applicant->last_name ?? null,
            'email' => $schedule->applicant->email ?? null,
            'contact_number' => $schedule->applicant->contact_number ?? null,
            'exam_date' => $schedule->exam_date,
            'exam_time_from' => $schedule->exam_time_from,
            'exam_time_to' => $schedule->exam_time_to,
            'exam_score' => $schedule->exam_score,
            'exam_status' => $schedule->exam_status,
            'academic_program_id' => $schedule->academic_program_id,
            'course_name' => $schedule->course_name,
            'created_at' => $schedule->created_at,
        ];
    });

    return response()->json([
        'isSuccess' => true,
        'message' => 'Exam schedules list ordered by creation date.',
        'exam_info' => $examInfo,
        'meta' => [
            'current_page' => $paginated->currentPage(),
            'per_page' => $paginated->perPage(),
            'total' => $paginated->total(),
            'last_page' => $paginated->lastPage(),
        ]
    ]);
}


    public function getAdmissions(Request $request)
{
    try {
    $query = admissions::with(['academic_program', 'schoolCampus', 'school_years'])
        ->where('is_archived', '0')
        ->orderBy('created_at', 'desc'); 

        // Search by keyword
        if ($request->has('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('academic_year', 'like', "%$search%")
                    ->orWhere('first_name', 'like', "%$search%")
                    ->orWhere('last_name', 'like', "%$search%");
            });
        }

        // Filters
        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        if ($request->has('school_campus')) {
            $query->where('school_campus', $request->school_campus);
        }

        if ($request->has('academic_program')) {
            $query->where('academic_program', $request->academic_program);
        }

        if ($request->has('academic_year')) {
            $query->where('academic_year', $request->academic_year);
        }

        $admissions = $query->paginate(10);

       $admissionsData = $admissions->map(function ($admission) {
        $storagePath = 'storage/';

    return [
        'id' => $admission->id,
        'test_permit_no' => $admission->test_permit_no,
        'applicant_number' => $admission->applicant_number,
        'status' => $admission->status,
        'first_name' => $admission->first_name,
        'middle_name' => $admission->middle_name,
        'last_name' => $admission->last_name,
        'suffix' => $admission->suffix,
        'full_name' => trim($admission->first_name . ' ' . $admission->middle_name . ' ' . $admission->last_name . ' ' . $admission->suffix),
        'gender' => $admission->gender,
        'birthdate' => $admission->birthdate,
        'birthplace' => $admission->birthplace,
        'civil_status' => $admission->civil_status,
        'email' => $admission->email,
        'grade_level' => $admission->grade_level_id,
        'contact_number' => $admission->contact_number,
        'telephone_number' => $admission->telephone_number,
        'street_address' => $admission->street_address,
        'province' => $admission->province,
        'city' => $admission->city,
        'barangay' => $admission->barangay,
        'nationality' => $admission->nationality,
        'religion' => $admission->religion,
        'ethnic_affiliation' => $admission->ethnic_affiliation,
        'is_4ps_member' => $admission->is_4ps_member,
        'is_insurance_member' => $admission->is_insurance_member,
        'is_vaccinated' => $admission->is_vaccinated,
        'is_indigenous' => $admission->is_indigenous,
        'application_type' => $admission->application_type,
        'lrn' => $admission->lrn,
        'last_school_attended' => $admission->last_school_attended,
        'remarks' => $admission->remarks,
        
        // Files as full URLs
     'good_moral' => $admission->good_moral ? asset($admission->good_moral) : null,
    'form_137' => $admission->form_137 ? asset($admission->form_137) : null,
    'form_138' => $admission->form_138 ? asset($admission->form_138) : null,
    'birth_certificate' => $admission->birth_certificate ? asset($admission->birth_certificate) : null,
    'certificate_of_completion' => $admission->certificate_of_completion ? asset($admission->certificate_of_completion) : null,


        'grade_level' => $admission->grade_level_id,
        'guardian_name' => $admission->guardian_name,
        'guardian_contact' => $admission->guardian_contact,
        'mother_name' => $admission->mother_name,
        'mother_contact' => $admission->mother_contact,
        'father_name' => $admission->father_name,
        'father_contact' => $admission->father_contact,
        'blood_type' => $admission->blood_type,

        // Related Names
        'academic_program' => optional($admission->academic_program)->course_name,
        'school_campus' => optional($admission->schoolCampus)->campus_name,
        'academic_year' => optional($admission->school_years)->school_year,
    ];
});

        return response()->json([
            'isSuccess' => true,
            'admissions' => $admissionsData,
            'pagination' => [
                'current_page' => $admissions->currentPage(),
                'per_page' => $admissions->perPage(),
                'total' => $admissions->total(),
                'last_page' => $admissions->lastPage(),
            ],
        ]);
    } catch (Throwable $e) {
        return response()->json([
            'isSuccess' => false,
            'message' => 'Failed to retrieve admissions.',
            'error' => $e->getMessage(),
        ], 500);
    }
}









    public function getAdmissionById($id)
    {
        try {
            $admission = admissions::where('id', $id)
                ->where('is_archived', 0)
                ->first();

            if (!$admission) {
                return response()->json([
                    'isSuccess' => false,
                    'message' => 'Admission not found.',
                ], 404);
            }

            return response()->json([
                'isSuccess' => true,
                'message' => 'Admission retrieved successfully.',
                'admission' => $admission,
            ]);
        } catch (Throwable $e) {
            return response()->json([
                'isSuccess' => false,
                'message' => 'Failed to retrieve admission.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

 
public function getAllEnrollments(Request $request)
{
    try {
        $perPage = $request->get('per_page', 10);

        // Base query
        $query = students::with([
            'examSchedule.applicant.gradeLevel',
            'examSchedule.applicant.course',
            'examSchedule.applicant.campus',
            'section'
        ]);

        // Search by keyword (student number or applicant name)
        if ($request->has('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('student_number', 'like', "%{$search}%")
                  ->orWhereHas('examSchedule.applicant', function ($q2) use ($search) {
                      $q2->where('first_name', 'like', "%{$search}%")
                         ->orWhere('last_name', 'like', "%{$search}%");
                  });
            });
        }

        // Filters
        if ($request->has('campus')) {
            $campus = $request->campus;
            $query->whereHas('examSchedule.applicant.campus', function ($q) use ($campus) {
                $q->where('campus_name', $campus);
            });
        }

        if ($request->has('course')) {
            $course = $request->course;
            $query->whereHas('examSchedule.applicant.course', function ($q) use ($course) {
                $q->where('course_name', $course);
            });
        }

        if ($request->has('section')) {
            $section = $request->section;
            $query->whereHas('section', function ($q) use ($section) {
                $q->where('section_name', $section);
            });
        }

        // Paginate
        $students = $query->paginate($perPage);

        // Map results
        $enrollments = $students->map(function ($student) {
            $examSchedule = $student->examSchedule;
            $admission    = $examSchedule?->applicant;
            $courseId     = $student->course_id;

            // Get curriculum & subjects
            $curriculum = DB::table('curriculums')->where('course_id', $courseId)->first();
            $subjects = [];
            $totalUnits = 0;

            if ($curriculum) {
                $currSubjects = DB::table('curriculum_subject as cs')
                    ->join('subjects as s', 'cs.subject_id', '=', 's.id')
                    ->where('cs.curriculum_id', $curriculum->id)
                    ->select('s.id as subject_id', 's.subject_name', 's.units')
                    ->get();

                foreach ($currSubjects as $subj) {
                    $subjects[] = [
                        'subject_id' => $subj->subject_id,
                        'subject_name' => $subj->subject_name,
                        'units' => $subj->units,
                    ];
                    $totalUnits += $subj->units;
                }
            }

            return [
                'id' => $student->id,
                'student_number' => $student->student_number,
                'status' => $student->enrollment_status,
                'payment_status' => $student->payment_status,
                'grade_level' => $admission?->gradeLevel?->grade_level,
                'course' => $admission?->course?->course_name,
                'campus' => $admission?->campus?->campus_name,
                'tuition_fee' => $student->tuition_fee,
                'misc_fee' => $student->misc_fee,
                'units_fee' => $student->units_fee,
                'exam' => [
                    'exam_id' => $examSchedule?->id,
                    'exam_date' => $examSchedule?->exam_date,
                    'exam_status' => $examSchedule?->exam_status,
                    'exam_score' => $examSchedule?->exam_score,
                ],
                'applicant' => [
                    'applicant_id' => $admission?->id,
                    'first_name' => $admission?->first_name,
                    'last_name' => $admission?->last_name,
                    'email' => $admission?->email,
                    'contact' => $admission?->contact_number,
                ],
                'section' => [
                    'section_id' => $student->section?->id,
                    'section_name' => $student->section?->section_name,
                ],
                'curriculum' => $curriculum ? [
                    'id' => $curriculum->id,
                    'name' => $curriculum->curriculum_name,
                    'description' => $curriculum->curriculum_description,
                ] : null,
                'subjects' => $subjects,
                'total_units' => $totalUnits,
            ];
        });

        return response()->json([
            'isSuccess' => true,
            'data' => $enrollments,
            'pagination' => [
                'current_page' => $students->currentPage(),
                'per_page' => $students->perPage(),
                'total' => $students->total(),
                'last_page' => $students->lastPage(),
            ],
        ]);
    } catch (Throwable $e) {
        return response()->json([
            'isSuccess' => false,
            'message' => 'Failed to retrieve enrollments.',
            'error' => $e->getMessage(),
        ], 500);
    }
}






    public function sendManualAdmissionEmail(Request $request)
    {
        $request->validate([
            'emails' => 'required|array',
            'emails.*' => 'email',
            'subject' => 'required|string',
            'custom_message' => 'required|string',
        ]);

        $failedEmails = [];

        foreach ($request->emails as $email) {
            $admission = admissions::where('email', $email)->first();

            if (!$admission) {
                $failedEmails[] = $email;
                continue;
            }

            $logoUrl = 'https://fileport.io/get/Cf2MRDiXkoVWEMqqlioHTQ09tN9GssRpbtZl4TCuUgneQmez_cby-fPw5cG3IqipODFod8HsL1pa3wPjOllBRufHmN8q62OOGtJH1A5jRTuXVbqlQDxjkWzC8_IWawy3O6OosYMZhtNaSesNASGE55FfUls1iLAgBiNJnZrovFOsuJRYKVqGhZ2UayJR2fuoVn9W8X0_aLwVcbf0Qo8OEuDF8r9HBOg69oGxWGk6_YWsT-0GeqHzIKzVg1Xh6EPvaR7UbkJCrXUz7u_1W5IsX9'; // Replace with your actual logo URL

            $htmlContent = "
        <html>
        <head>
            <style>
                body {
                    font-family: Arial, sans-serif;
                    background-color: #f7f9fb;
                    color: #333;
                    padding: 20px;
                }
                .email-container {
                    max-width: 600px;
                    margin: auto;
                    background: #ffffff;
                    padding: 30px;
                    border-radius: 8px;
                    box-shadow: 0 2px 5px rgba(0,0,0,0.1);
                }
                .header {
                    text-align: center;
                    margin-bottom: 30px;
                }
                .footer {
                    margin-top: 40px;
                    font-size: 12px;
                    color: #888;
                    text-align: center;
                }
            </style>
        </head>
        <body>
            <div class='email-container'>
                <div class='header'>
                    <img src='{$logoUrl}' alt='Logo' height='80' />
                    <h2>Entrance Examination</h2>
                </div>
                <p>Dear <strong>{$admission->first_name}</strong>,</p>
                <p>{$request->custom_message}</p>
                <p><strong>Program Applied:</strong> {$admission->academic_program}</p>
                <p>We appreciate your interest and look forward to your success with us.</p>
                <p>Best regards,<br><strong>Admissions Office</strong></p>

                <div class='footer'>
                    &copy; " . date('Y') . " Your Institution. All rights reserved.
                </div>
            </div>
        </body>
        </html>
        ";

            try {
                Mail::send([], [], function ($message) use ($email, $htmlContent, $request) {
                    $message->to($email)
                        ->subject($request->subject)
                        ->setBody($htmlContent, 'text/html');
                });
            } catch (\Exception $e) {
                $failedEmails[] = $email;
            }
        }

        return response()->json([
            'isSuccess' => count($failedEmails) === 0,
            'message' => count($failedEmails) === 0
                ? 'Emails sent successfully.'
                : 'Some emails failed to send.',
            'failed' => $failedEmails
        ]);
    }


    public function sendCustomEmail(Request $request)
    {
        $validated = $request->validate([
            'recipient_email' => 'required|email',
            'recipient_name' => 'required|string|max:255',
            'email_type' => 'required|string', // e.g., entrance_exam, interview_schedule, etc.
            'custom_data' => 'nullable|array'  // contains placeholders like exam_date, location, etc.
        ]);

        $template = $this->getEmailTemplate($validated['email_type']);

        if (!$template) {
            return response()->json([
                'isSuccess' => false,
                'message' => 'Invalid email template selected.',
            ], 400);
        }

        // Replace placeholders
        $placeholders = $validated['custom_data'] ?? [];
        $placeholders['name'] = $validated['recipient_name'];
        foreach ($placeholders as $key => $value) {
            $template = str_replace('{{' . $key . '}}', $value, $template);
        }

        // Send the email using Mail::html instead of Mail::raw
        Mail::html($template, function ($message) use ($validated) {
            $message->to($validated['recipient_email'], $validated['recipient_name'])
                ->subject(Str::title(str_replace('_', ' ', $validated['email_type'])));
        });

        return response()->json([
            'isSuccess' => true,
            'message' => 'Email sent successfully.',
        ]);
    }





    public function applyAdmission(Request $request)
    {
        try {
            $validated = $request->validate([
                'lrn'=> 'required|numeric',
                'surname' => 'required|string|max:50',
                'given_name' => 'required|string|max:50',
                'middle_name' => 'nullable|string|max:50',
                'middle_initial' => 'nullable|string|max:5',
                'user_type' => 'nullable|string',
                'suffix' => 'nullable|string|max:10',
                'date_of_birth' => 'required|date',
                'place_of_birth' => 'required|string|max:100',
                'gender' => 'required|string|max:10',
                'civil_status' => 'required|string|max:20',
                'blood_type'=> 'required|string',

                'street_address' => 'required|string|max:255',
                'province' => 'required|string|max:100',
                'city' => 'required|string|max:100',
                'barangay' => 'required|string|max:100',

                'nationality' => 'required|string|max:50',
                'religion' => 'required|string|max:50',
                'ethnic_affiliation' => 'nullable|string|max:50',
                'telephone_number' => 'nullable|string|max:15',
                'mobile_number' => 'required|string|max:15',
                'email' => 'required|email|max:100|unique:admissions,email',

                'is_4ps_member' => 'required|string',
                'is_insurance_member' => 'required|string',
                'is_vaccinated' => 'required|string',
                'is_indigenous' => 'required|string',

                'academic_program_id' => 'required|exists:courses,id',
                'school_campus_id' => 'required|exists:school_campus,id',
                'academic_year_id' => 'required|exists:school_years,id',
                'grade_level_id' => 'required|exists:grade_levels,id',
                'semester' => 'nullable|string|max:50',
                'application_type' => 'required|string|max:50',

                'guardian_name' => 'nullable|string|max:100',
                'guardian_contact' => 'nullable|string|max:20',
                'mother_name' => 'nullable|string|max:100',
                'mother_contact' => 'nullable|string|max:20',
                'father_name' => 'nullable|string|max:100',
                'father_contact' => 'nullable|string|max:20',


                'last_school_attended' => 'nullable|string|max:255',
                'remarks' => 'nullable|string|max:255',

                'form_137' => 'nullable|file|mimes:pdf,jpg,jpeg,png|max:10240',
                'form_138' => 'nullable|file|mimes:pdf,jpg,jpeg,png|max:10240',
                'birth_certificate' => 'nullable|file|mimes:pdf,jpg,jpeg,png|max:10240',
                'good_moral' => 'nullable|file|mimes:pdf,jpg,jpeg,png|max:10240',
                'certificate_of_completion' => 'nullable|file|mimes:pdf,jpg,jpeg,png|max:10240',
            ]);

            // Fetch names from related tables using IDs
            $campusName = school_campus::find($validated['school_campus_id'])->campus_name;
            $academicYear = school_years::find($validated['academic_year_id'])->year;
            $programName = courses::find($validated['academic_program_id'])->course_name;


            $applicantNumber = 'APLN-' . now()->format('YmdHis') . rand(100, 999);

            $admission = admissions::create([
                'account_id' => null,
                'applicant_number' => $applicantNumber,
                'academic_year_id' => $validated['academic_year_id'],
                'grade_level_id' => $validated['grade_level_id'] ?? null,
                'semester' => $validated['semester'] ?? null,
                'school_campus_id' => $validated['school_campus_id'],
                'application_type' => $validated['application_type'],
                'academic_program_id' => $validated['academic_program_id'],
                
                'lrn' => $validated['lrn'],
                'first_name' => $validated['given_name'],
                'middle_name' => $validated['middle_name'] ?? '',
                'last_name' => $validated['surname'],
                'suffix' => $validated['suffix'] ?? '',
                'gender' => $validated['gender'],
                'birthdate' => $validated['date_of_birth'],
                'birthplace' => $validated['place_of_birth'],
                'civil_status' => $validated['civil_status'],
                'email' => $validated['email'],
                'contact_number' => $validated['mobile_number'],
                'street_address' => $validated['street_address'],
                'province' => $validated['province'],
                'city' => $validated['city'],
                'barangay' => $validated['barangay'],
                'blood_type' =>$validated['blood_type'],

                'nationality' => $validated['nationality'],
                'religion' => $validated['religion'],
                'ethnic_affiliation' => $validated['ethnic_affiliation'] ?? null,
                'telephone_number' => $validated['telephone_number'] ?? null,
                'is_4ps_member' => $validated['is_4ps_member'],
                'is_insurance_member' => $validated['is_insurance_member'],
                'is_vaccinated' => $validated['is_vaccinated'],
                'is_indigenous' => $validated['is_indigenous'],

                'guardian_name' => $validated['guardian_name'] ?? null,
                'guardian_contact' => $validated['guardian_contact'] ?? null,
                'mother_name' => $validated['mother_name'] ?? null,
                'mother_contact' => $validated['mother_contact'] ?? null,
                'father_name' => $validated['father_name'] ?? null,
                'father_contact' => $validated['father_contact'] ?? null,


                'last_school_attended' => $validated['last_school_attended'] ?? null,
                'remarks' => $validated['remarks'] ?? null,
                'status' => 'pending',

                'form_137' => $this->saveFileToPublic($request, 'form_137', 'form_137'),
                'form_138' => $this->saveFileToPublic($request, 'form_138', 'form_138'),
                'birth_certificate' => $this->saveFileToPublic($request, 'birth_certificate', 'birth_cert'),
                'good_moral' => $this->saveFileToPublic($request, 'good_moral', 'good_moral'),
                'certificate_of_completion' => $this->saveFileToPublic($request, 'certificate_of_completion', 'completion_cert'),
            ]);

            // Send email
            $firstName = $validated['given_name'] ?? 'Applicant';
            $lastName = $validated['surname'] ?? '';
            $email = $validated['email'];
            $appointmentDate = now()->addDays(7)->format('m/d/Y'); // Example: 7 days from now

            Mail::html("
    <div style='font-family: Arial, sans-serif; max-width: 800px; margin: 0 auto;'>
        <h1 style='color: #2c3e50; text-align: center;'> SNL University</h1>
        
        <h2 style='color: #2c3e50;'>Good day¹</h2>
        
        <h3 style='color: #2c3e50;'>Dear Mr./Ms. {$lastName}, {$firstName},</h3>
        
        <p>This is to inform you that we already received your online application to SNL - Main Campus.</p>
        <p>Please take note of your applicant number: <strong>{$applicantNumber}</strong></p>
        
        <p>Your appointment schedule for the submission of the required documents will be on <strong>{$appointmentDate}</strong></p>
        
        <p>Documents to be submitted:</p>
        <ol type='A'>
            <li>A Certified True Copy (with school sets) of School Form D (Form 13b) in Grade 11.</li>
            <li>A Certified True Copy of Proof of Residency (Barangay Certificate)</li>
            <li>A Certified True Copy of Certification / Membership Certification / Barangay-issued Certificate / ID (if applicable) of the following:
                <ol type='1'>
                    <li>Member of an Indigenous Cultural Community (GCC) / Indigenous People (SP)</li>
                    <li>Member of Paramed Family's Pilipino Program (APO)</li>
                    <li>Student with Special Needs (SSN) and other types of disabilities</li>
                    <li>Graduate of Alternative Learning System (ALS) (Accreditation dump, Equivalency Assessment and Certification)</li>
                    <li>Child of Solo Parent (Solo parent ID)</li>
                    <li>Student with Exemplary Armies and Adabute Ability (certification from the School Head)</li>
                </ol>
            </li>
        </ol>
        
        <h4 style='color: #2c3e50;'>Grounds for Disqualifications of Application:</h4>
        <ol>
            <li>Misrepresentation of the information entered in any of the submitted forms (including but not limited to the application portal)</li>
            <li>Violation of the application instructions.</li>
            <li>Non-submission of documents as scheduled.</li>
        </ol>
        
        <h4 style='color: #2c3e50;'>NOTES:</h4>
        <ul>
            <li>Transferers and Richerts who attempt to apply through Freshmen definitions will be blacklisted in all SNL programs.</li>
            <li>Students who have been admitted and enrolled in any programs will only be granted an honorable dismissal once the semester starts.</li>
        </ul>
        
        <h4 style='color: #2c3e50;'>REMINDER:</h4>
        <p>Successful Applicants must submit the complete required documents on the exact date of their Appointment. A five-to-five Administer will be administered by the SNL Administers and Orientation Services office. Kindly check your Email regularly (inbox and spam) for updates.</p>
        
        <p>To view your application details, please <a href='https://yourdomain.com/application-status'>Click Here</a></p>
        
        <p style='margin-top: 30px;'>Sincerely,<br>Admissions Office<br>SNL University</p>
    </div>
", function ($message) use ($email, $applicantNumber) {
                $message->to($email)
                    ->subject('Admission Application Received - Applicant #' . $applicantNumber);
            });

            return response()->json([
                'isSuccess' => true,
                'message' => 'Admission application submitted successfully.',
                'admission' => $admission,
                'academic_program' => $programName,
                'school_campus' => $campusName,
                'academic_year' => $academicYear,
            ], 200);
        } catch (ValidationException $e) {
            return response()->json([
                'isSuccess' => false,
                'message' => 'Validation failed.',
                'errors' => $e->errors(),
            ], 422);
        } catch (Throwable $e) {
            return response()->json([
                'isSuccess' => false,
                'message' => 'Failed to submit admission application.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }



    
   public function acceptapplication(Request $request, $id)
{
    try {
        $user = Auth::user();
        if (!$user) {
            return response()->json([
                'isSuccess' => false,
                'message' => 'Unauthorized.',
            ], 401);
        }

        $admission = admissions::findOrFail($id);

        // Check if already approved
        if ($admission->status === 'approved') {
            return response()->json([
                'isSuccess' => false,
                'message' => 'This application has already been approved.',
            ]);
        }

        $admission->status = 'approved';
        $admission->save();

        $firstName = $admission->given_name ?? 'Applicant';
        $lastName = $admission->surname ?? '';
        $email = $admission->email;

        // Send confirmation email only if not previously approved
        if ($email) {
            Mail::html("
                <div style='font-family: Arial, sans-serif; max-width: 700px; margin: auto;'>
                    <h2>SNL University</h2>
                    <p>Dear Mr./Ms. {$lastName}, {$firstName},</p>

                    <p>Congratulations on completing your application.</p>

                    <p><strong>NOTE:</strong> You are requested to wait for further instructions from the SNL Admissions and Orientation Office for your Examination schedule.</p>

                    <p>The schedule of the examination will be sent to your registered email address. Kindly check your email regularly (inbox, spam, or junk).</p>

                    <p>Follow and regularly check the SNL Admissions and Orientation Services Facebook Page for further announcements. For inquiries, you may call us at 09******* local 1087 or email us at <a href='mailto:*******@***.com'>admissions@****.****.***</a>.</p>
                </div>
            ", function ($message) use ($email) {
                $message->to($email)->subject('SNL Application Confirmation');
            });
        }

        return response()->json([
            'isSuccess' => true,
            'message' => 'Application accepted and email sent.',
        ]);
    } catch (\Exception $e) {
        return response()->json([
            'isSuccess' => false,
            'message' => 'Error: ' . $e->getMessage(),
        ]);
    }
}



public function sendExamination(Request $request)
{
    try {
        try {
            $request->validate([
                'admission_ids' => 'required|array',
                'admission_ids.*' => 'exists:admissions,id',
                'exam_date' => 'required|date',
                'exam_time_from' => 'required|date_format:H:i',
                'exam_time_to' => 'required|date_format:H:i|after:exam_time_from',
                'building_id' => 'required|exists:campus_buildings,id',
                'room_id' => [
                    'required',
                    'exists:building_rooms,id',
                    function ($attribute, $value, $fail) use ($request) {
                        $room = \App\Models\building_rooms::find($value);
                        if (!$room || $room->building_id != $request->building_id) {
                            $fail('The selected room does not belong to the selected building.');
                        }
                    }
                ],
                'campus_id' => 'nullable|exists:school_campus,id',
            ]);
        } catch (ValidationException $e) {
            return response()->json([
                'isSuccess' => false,
                'message' => 'Validation failed.',
                'errors' => $e->errors() // shows all failed fields with reasons
            ], 422);
        }


        $examDate = $request->exam_date;
        $results = [];

        $building = campus_buildings::find($request->building_id);
        $room = building_rooms::find($request->room_id);

        foreach ($request->admission_ids as $id) {
            try {
                $admission = admissions::with(['academic_program', 'schoolCampus', 'school_years'])->findOrFail($id);

                if (strtolower($admission->status) === 'rejected') {
                    $results[] = [
                        'admission_id' => $id,
                        'status' => 'skipped',
                        'message' => 'Applicant is rejected and will not be scheduled.',
                    ];
                    continue;
                }

                if (!$admission->test_permit_no) {
                    $prefix = "SNL-";
                    $paddedId = str_pad($admission->id, 5, '0', STR_PAD_LEFT);
                    $admission->test_permit_no = $prefix . $paddedId;
                    $admission->save();
                }

                $schedule = exam_schedules::where('admission_id', $admission->id)->first();
                $wasAlreadySent = $schedule ? $schedule->exam_sent : false;

                exam_schedules::updateOrCreate(
                    ['admission_id' => $admission->id],
                    [
                       
                        'test_permit_no' => $admission->test_permit_no,
                        'room_id' => $room->id,
                        'building_id' => $building->id,
                        'campus_id' => $request->campus_id,
                        'course_id' => $request->course_id ?? null,
                        'exam_time_from' => $request->exam_time_from,
                        'exam_time_to' => $request->exam_time_to,
                        'exam_date' => $examDate,
                        'academic_year' => $admission->school_years->school_year,
                        'exam_sent' => $wasAlreadySent,
                    ]
                );

                if (!$wasAlreadySent && $admission->email) {
                    $examDateFormatted = date('F d, Y', strtotime($examDate));
                    $timeFormatted = date('h:i A', strtotime($request->exam_time_from)) . ' – ' . date('h:i A', strtotime($request->exam_time_to));
                    $testingCenter = $admission->schoolCampus->campus_name ?? 'SNL – Main Campus';

                    Mail::html("
                        <div style='font-family: Arial, sans-serif; max-width: 700px; margin: auto;'>
                            <h2>SNL University Exam Schedule</h2>
                            <p>Good day!</p>
                            <p>
                                Dear {$admission->last_name}, {$admission->first_name},<br>
                                Course: {$admission->academic_program->course_name} at SNL – {$testingCenter}
                            </p>
                            <p>Please be informed of your schedule for the Admission Test on <strong>{$examDateFormatted}</strong>.</p>
                            <p>
                                <strong>Test Permit No:</strong> {$admission->test_permit_no}<br>
                                <strong>Room Assignment:</strong> {$room->room_name}<br>
                                <strong>Building:</strong> {$building->building_name}<br>
                                <strong>Time:</strong> {$timeFormatted}<br>
                                <strong>Testing Center:</strong> SNL – {$testingCenter}
                            </p>
                        </div>
                    ", function ($message) use ($admission) {
                        $message->to($admission->email)->subject('SNL Exam Schedule Notification');
                    });

                    exam_schedules::where('admission_id', $admission->id)->update(['exam_sent' => true]);

                    $results[] = [
                        'admission_id' => $id,
                        'status' => 'exam_sent',
                    ];
                } else {
                    $results[] = [
                        'admission_id' => $id,
                        'status' => 'skipped',
                        'message' => 'Email already sent previously.',
                    ];
                }
            } catch (\Exception $ex) {
                $results[] = [
                    'admission_id' => $id,
                    'status' => 'error',
                    'message' => $ex->getMessage(),
                ];
            }
        }

        return response()->json([
            'isSuccess' => true,
            'message' => 'Bulk exam scheduling completed.',
            'results' => $results,
        ]);

    } catch (\Exception $e) {
        return response()->json([
            'isSuccess' => false,
            'message' => 'Failed to send exam schedules.',
            'error' => $e->getMessage(),
        ]);
    }
}






 public function reserveSlot(Request $request, $id)
{
    try {
        // Validate schedule date
        $request->validate([
            'schedule_date' => 'required|date|after_or_equal:today',
        ]);

        // Fetch admission with course
        $admission = admissions::with('course')->findOrFail($id);

        if (!$admission->course) {
            return response()->json([
                'isSuccess' => false,
                'message' => 'Missing course data.',
            ]);
        }

        $currentYear = Carbon::now()->year;
        $nextYear = $currentYear + 1;
        $academicYearText = $currentYear . '-' . $nextYear;

        // Format dynamic schedule range (e.g. June 1 to 15 of current year)
        $scheduleStart = Carbon::create($currentYear, 6, 1)->format('F d');
        $scheduleEnd = Carbon::create($currentYear, 6, 15)->format('d, Y');

        // Create reservation
        $reservation = new AdmissionReservation();
        $reservation->admission_id = $id;
        $reservation->schedule_date = $request->schedule_date;
        $reservation->academic_year_id = $admission->academic_year_id;
        $reservation->reservation_code = strtoupper(uniqid('RES-'));
        $reservation->save();

        // Use data directly from admissions table
        $student_name = $admission->given_name . ' ' . $admission->surname;
        $reservation_date = date('F d, Y', strtotime($request->schedule_date));

        // Email HTML content
        $htmlContent = '
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset="UTF-8">
            <title>SNL Online Reservation</title>
            <style>
                body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; max-width: 600px; margin: 0 auto; padding: 20px; }
                .header { color: #0056b3; border-bottom: 2px solid #0056b3; padding-bottom: 10px; }
                .footer { margin-top: 20px; font-size: 0.9em; color: #666; border-top: 1px solid #eee; padding-top: 10px; }
                .note { background-color: #f8f9fa; padding: 10px; border-left: 4px solid #6c757d; margin: 15px 0; }
            </style>
        </head>
        <body>
            <div class="header">
                <h1>SNL University Online Reservation</h1>
                <p>www.prismsouth.org.au/commons/cn</p>
            </div>

            <h2>Computational Quarters for AZ '. $academicYearText. ' </h2>

            <h3>Name: ' . htmlspecialchars($student_name) . '</h3>
            <p>Course: ' . htmlspecialchars($admission->course->course_name) . '</p>

            <p>In order for the Admission Office to facilitate your pre-enrollment, you must proceed to the SNL Admission and Registration Office (Main Campus) on your <strong>specified schedule date</strong> from' . $scheduleStart . ' to ' . $scheduleEnd . '..</p>

            <p>Please bring the following:</p>
            <ol>
                <li>Printed copy of this reservation form</li>
                <li>Visit the Infirmary for Medical/Dental Examination and submit:
                    <ul>
                        <li>Completed Student Health Assessment Form</li>
                        <li>Original copy of Chest X-ray result</li>
                        <li>Medical Certificate (if applicable)</li>
                        <li>Valid ID (if applicable)</li>
                    </ul>
                </li>
            </ol>

            <div class="note">
                <p><strong>Important:</strong> Failure to visit on your specified date will result in cancellation of your reservation and you will lose the opportunity to be admitted to the University.</p>
            </div>

            <h3>RESERVATION SCHEDULE AY ' . $academicYearText . '</h3>
            <p><strong>Your reservation date:</strong> ' . htmlspecialchars($reservation_date) . '</p>
            <p>Please be guided accordingly.</p>
            <p>Thank you for choosing SNL!</p>

            <div class="footer">
                <p><em>This is an automatically generated email - please do not reply.</em></p>
            </div>
        </body>
        </html>';

        // Send email
        Mail::send([], [], function ($message) use ($admission, $htmlContent) {
            $message->to($admission->email)
                    ->subject('SNL Online Reservation Confirmation')
                    ->setBody($htmlContent, 'text/html');
        });

        return response()->json([
            'isSuccess' => true,
            'message' => 'Reservation created and email sent successfully.',
        ]);

    } catch (ValidationException $e) {
        return response()->json([
            'isSuccess' => false,
            'message' => 'Validation failed.',
            'errors' => $e->errors(),
        ], 422);
    } catch (\Exception $e) {
        return response()->json([
            'isSuccess' => false,
            'message' => 'Reservation failed.',
            'error' => $e->getMessage(),
        ], 500);
    }
}




public function inputExamScores(Request $request)
{
    $data = $request->all(); // expects an array of {id, exam_score}
    $results = [];
    $passingScore = 75;

    foreach ($data as $item) {
        try {
            if (!isset($item['id']) || !isset($item['exam_score'])) {
                throw new \Exception("Both id and exam_score are required");
            }

            if (!is_numeric($item['exam_score']) || $item['exam_score'] < 0 || $item['exam_score'] > 100) {
                throw new \Exception("Invalid score for schedule ID {$item['id']}");
            }

            $schedule = exam_schedules::with('applicant')->findOrFail($item['id']);
            $schedule->exam_score = $item['exam_score'];
            $schedule->exam_status = ($item['exam_score'] >= $passingScore) ? 'passed' : 'reconsider';
            $schedule->save();

            $results[] = [
                'schedule_id' => $schedule->id,
                'status' => 'success',
                'exam_score' => $schedule->exam_score,
                'exam_status' => $schedule->exam_status,
                'email_sent' => $schedule->exam_score_sent
            ];
        } catch (\Exception $e) {
            $results[] = [
                'schedule_id' => $item['id'] ?? null,
                'status' => 'failed',
                'message' => $e->getMessage(),
            ];
        }
    }

    return response()->json([
        'isSuccess' => true,
        'message' => 'Bulk exam scores processed.',
        'results' => $results,
    ]);
}



    public function sendExamResult($scheduleId)
{
    try {
        $schedule = exam_schedules::with('applicant')->findOrFail($scheduleId);

        if (!$schedule->applicant || !$schedule->applicant->email) {
            throw new \Exception("Applicant or email not found for schedule ID {$scheduleId}");
        }

        if ($schedule->exam_status !== 'passed') {
            throw new \Exception("Student did not pass the exam, no email will be sent.");
        }

        if ($schedule->exam_score_sent) {
            throw new \Exception("Exam result email already sent.");
        }

        $studentName = trim($schedule->applicant->first_name . ' ' . $schedule->applicant->last_name);
        $score = $schedule->exam_score;

        $htmlContent = "
        <html>
            <body style='font-family: Arial, sans-serif; color: #333;'>
                <div style='max-width: 600px; margin: auto; padding: 20px; border: 1px solid #ddd; border-radius: 8px;'>
                    <h2 style='color: #004aad;'>SNL Examination Result</h2>
                    <p>Dear <strong>{$studentName}</strong>,</p>
                    <p>We are pleased to inform you that you have <strong style='color: green;'>passed</strong> the SNL entrance examination.</p>
                    <p>Your exam score: <strong>{$score}</strong></p>
                    <p>Congratulations on this achievement! Our admissions team will contact you with the next steps in the enrollment process.</p>
                    <br>
                    <h3 style='color: #004aad;'>Important Enrollment Requirements</h3>
                    <p>Please prepare and bring the <strong>original copies</strong> of the following documents when you visit the admissions office:</p>
                    <ul>
                        <li>Form 137 (High School Permanent Record)</li>
                        <li>Form 138 (Report Card)</li>
                        <li>Certificate of Good Moral Character</li>
                        <li>Certificate of Completion (COC)</li>
                        <li>PSA-issued Birth Certificate</li>
                    </ul>
                    <p>Failure to present these documents may delay your enrollment process.</p>
                    <br>
                    <p>Best regards,</p>
                    <p><strong>SNL Admissions Office</strong><br>
                    Smart National Learning School<br>
                    Email: admissions@snl.edu<br>
                    Phone: (123) 456-7890</p>
                </div>
            </body>
        </html>
        ";

        Mail::send([], [], function ($message) use ($schedule, $studentName, $htmlContent) {
            $message->to($schedule->applicant->email, $studentName)
                ->subject('SNL Examination Result')
                ->setBody($htmlContent, 'text/html');
        });

        $schedule->exam_score_sent = 1;
        $schedule->save();

        return response()->json([
            'isSuccess' => true,
            'message' => "Exam result email sent to {$studentName} ({$schedule->applicant->email})"
        ]);
    } catch (\Exception $e) {
        return response()->json([
            'isSuccess' => false,
            'message' => 'Error: ' . $e->getMessage()
        ], 500);
    }
}



    public function sendBulkExamResults(Request $request)
{
    $validated = $request->validate([
        'schedule_ids' => 'required|array',
        'schedule_ids.*' => 'integer|exists:exam_schedules,id',
    ]);

    $results = [];
    foreach ($validated['schedule_ids'] as $id) {
        try {
            $response = $this->sendExamResult($id, true); // pass "true" to indicate it's called internally
            $results[] = [
                'schedule_id' => $id,
                'status' => 'success',
                'message' => $response->getData()->message ?? 'Sent',
            ];
        } catch (\Exception $e) {
            $results[] = [
                'schedule_id' => $id,
                'status' => 'failed',
                'message' => $e->getMessage(),
            ];
        }
    }

    return response()->json([
        'isSuccess' => true,
        'message' => 'Bulk exam result emails processed.',
        'results' => $results,
    ]);
}



    public function getExamScoreSummary()
{
    $summary = exam_schedules::selectRaw("
        SUM(CASE WHEN exam_status = 'passed' THEN 1 ELSE 0 END) as passed,
        SUM(CASE WHEN exam_status = 'reconsider' THEN 1 ELSE 0 END) as reconsider
    ")->first();

    return response()->json([
        'isSuccess' => true,
        'message' => 'Exam scores summary retrieved.',
        'summary' => [
            'passed' => $summary->passed,
            'reconsider' => $summary->reconsider,
        ]
    ]);
}



    public function rejectAdmission(Request $request, $id)
    {
        try {
            $rejector = auth()->user();
            $admission = admissions::findOrFail($id);

            // Update admission status
            $admission->status = 'rejected';
            $admission->save();

            return response()->json([
                'isSuccess' => true,
                'message' => 'Admission rejected and email sent successfully.',
                'admission' => $admission,
            ], 200);
        } catch (ModelNotFoundException $e) {
            return response()->json([
                'isSuccess' => false,
                'message' => 'Admission not found.',
            ], 404);
        } catch (Throwable $e) {
            return response()->json([
                'isSuccess' => false,
                'message' => 'Failed to reject admission.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function deleteAdmission($id)
    {
        try {
            $admission = admissions::findOrFail($id);

            // Soft delete the admission
            $admission->is_archived = 1;
            $admission->save();

            return response()->json([
                'isSuccess' => true,
                'message' => 'Admission deleted successfully.',
            ], 200);
        } catch (ModelNotFoundException $e) {
            return response()->json([
                'isSuccess' => false,
                'message' => 'Admission not found.',
            ], 404);
        } catch (Throwable $e) {
            return response()->json([
                'isSuccess' => false,
                'message' => 'Failed to delete admission.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }


    //HELPERS
private function saveFileToPublic(Request $request, $field, $prefix)
{
    if ($request->hasFile($field)) {
        $file = $request->file($field);
        $directory = public_path('admission_files');
        if (!file_exists($directory)) {
            mkdir($directory, 0755, true);
        }
        $filename = $prefix . '_' . uniqid() . '.' . $file->getClientOriginalExtension();
        $file->move($directory, $filename);
        return 'admission_files/' . $filename;
    }
    return null;
}


    //Dropdowns
    public function getAdmissionStatuses()
    {
        $statuses = admissions::where('is_archived', 0)
            ->select('id', 'status')
            ->get()
            ->groupBy('status')
            ->map(function ($items, $status) {
                $first = $items->first();
                return [
                    'id' => $first->id,
                    'status' => ucfirst($status),
                ];
            })
            ->values();

        return response()->json([
            'isSuccess' => true,
            'message' => 'Admission statuses retrieved successfully.',
            'statuses' => $statuses
        ]);
    }

    public function getAcademicProgramsDropdown()
    {
        try {
            $data = courses::select('id', 'course_name')->get();

            return response()->json([
                'isSuccess' => true,
                'message' => 'Academic programs fetched successfully.',
                'academic_programs' => $data
            ]);
        } catch (Exception $e) {
            return response()->json([
                'isSuccess' => false,
                'message' => 'Failed to fetch academic programs.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    

    public function getAcademicYearsDropdown()
    {
        try {
            $data = school_years::select('id', 'school_year', 'semester')->get();

            return response()->json([
                'isSuccess' => true,
                'message' => 'Academic years fetched successfully.',
                'academic_years' => $data
            ]);
        } catch (Exception $e) {
            return response()->json([
                'isSuccess' => false,
                'message' => 'Failed to fetch academic years.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

public function getUniqueSchoolYearsDropdown()
{
    try {
        $data = school_years::selectRaw("id, CONCAT(TRIM(school_year), ' ', TRIM(semester)) as academic_year")
            ->orderBy('school_year', 'desc')
            ->get();

        return response()->json([
            'isSuccess' => true,
            'message' => 'School years fetched successfully.',
            'academic_years' => $data
        ]);
    } catch (\Exception $e) {
        return response()->json([
            'isSuccess' => false,
            'message' => 'Failed to fetch school years.',
            'error' => $e->getMessage(),
        ], 500);
    }
}





// Get campuses for first dropdown
        public function getCampusDropdown()
        {
            try {
                $campuses = school_campus::select('id', 'campus_name')->get();

                return response()->json([
                    'isSuccess' => true,
                    'message' => 'Campuses fetched successfully.',
                    'campuses' => $campuses
                ]);
            } catch (\Exception $e) {
                return response()->json([
                    'isSuccess' => false,
                    'message' => 'Failed to fetch campuses.',
                    'error' => $e->getMessage(),
                ], 500);
            }
        }

        // Get buildings for selected campus
        public function getBuildingsByCampus($campusId)
        {
            try {
                $buildings = campus_buildings::where('campus_id', $campusId)
                    ->select('id', 'building_name')
                    ->get();

                return response()->json([
                    'isSuccess' => true,
                    'message' => 'Buildings fetched successfully.',
                    'buildings' => $buildings
                ]);
            } catch (\Exception $e) {
                return response()->json([
                    'isSuccess' => false,
                    'message' => 'Failed to fetch buildings.',
                    'error' => $e->getMessage(),
                ], 500);
            }
        }

        // Existing rooms function (unchanged)
        public function getByBuilding($buildingid)
        {
            $rooms = building_rooms::where('building_id', $buildingid)->get();
            return response()->json($rooms);
        }

}
