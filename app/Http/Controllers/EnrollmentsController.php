<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\enrollments;
use Illuminate\Support\Facades\Validator;
use Illuminate\validation\Rule;
use App\Models\admissions;
use App\Models\sections;
use App\Models\curriculums;
use Illuminate\Support\Facades\Auth;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Mail;
use App\Models\accounts;
use App\Models\courses;
use App\Models\students;
use App\Models\exam_schedules;
use App\Models\subjects;
use Illuminate\Support\Facades\Hash;
use Carbon\Carbon;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Validation\ValidationException;
use Throwable;


class EnrollmentsController extends Controller
{

    public function getExamineesResult(Request $request)
{
    try {
        $perPage = $request->input('per_page', 5); // default 10 per page
        $page = $request->input('page', 1);

        // Fetch schedules with applicant/admission details
        $query = exam_schedules::with([
            'applicant.academic_program:id,course_name',
            'room:id,room_name',
            'building:id,building_name',
            'campus:id,campus_name'
        ])
        ->whereNotNull('exam_score'); 

        // Optional filters
        if ($request->has('search')) {
            $search = $request->search;
            $query->whereHas('applicant', function ($q) use ($search) {
                $q->where('first_name', 'like', "%$search%")
                  ->orWhere('last_name', 'like', "%$search%")
                  ->orWhere('email', 'like', "%$search%")
                  ->orWhere('contact_number', 'like', "%$search%");
            });
        }

        // Order newest exam first
        $query->orderBy('created_at', 'desc');

        $schedules = $query->paginate($perPage, ['*'], 'page', $page);

        // Transform into flat list of students + exam info
        $data = $schedules->map(function ($schedule) {
            $admission = $schedule->applicant;
            $storagePath = 'storage/';

            return [
                // Exam Schedule Details
                'id'       => $schedule->id,
                'test_permit_no'    => $schedule->test_permit_no,
                'exam_date'         => $schedule->exam_date,
                'exam_time_from'    => $schedule->exam_time_from,
                'exam_time_to'      => $schedule->exam_time_to,
                'exam_score'        => $schedule->exam_score,
                'exam_status'       => $schedule->exam_status,
                'room_name'         => optional($schedule->room)->room_name,
                'building_name'     => optional($schedule->building)->building_name,
                'campus_name'       => optional($schedule->campus)->campus_name,

                // Full Admission Details
                'admission_id'      => $admission->id ?? null,
                'first_name'        => $admission->first_name ?? null,
                'middle_name'       => $admission->middle_name ?? null,
                'last_name'         => $admission->last_name ?? null,
                'suffix'            => $admission->suffix ?? null,
                'full_name'         => trim(($admission->first_name ?? '') . ' ' . ($admission->middle_name ?? '') . ' ' . ($admission->last_name ?? '') . ' ' . ($admission->suffix ?? '')),
                'gender'            => $admission->gender ?? null,
                'birthdate'         => $admission->birthdate ?? null,
                'birthplace'        => $admission->birthplace ?? null,
                'civil_status'      => $admission->civil_status ?? null,
                'email'             => $admission->email ?? null,
                'contact_number'    => $admission->contact_number ?? null,
                'telephone_number'  => $admission->telephone_number ?? null,
                'street_address'    => $admission->street_address ?? null,
                'province'          => $admission->province ?? null,
                'city'              => $admission->city ?? null,
                'barangay'          => $admission->barangay ?? null,
                'nationality'       => $admission->nationality ?? null,
                'religion'          => $admission->religion ?? null,
                'ethnic_affiliation'=> $admission->ethnic_affiliation ?? null,
                'is_4ps_member'     => $admission->is_4ps_member ?? null,
                'is_insurance_member'=> $admission->is_insurance_member ?? null,
                'is_vaccinated'     => $admission->is_vaccinated ?? null,
                'is_indigenous'     => $admission->is_indigenous ?? null,
                'application_type'  => $admission->application_type ?? null,
                'lrn'               => $admission->lrn ?? null,
                'last_school_attended' => $admission->last_school_attended ?? null,
                'remarks'           => $admission->remarks ?? null,
                'grade_level'       => $admission->grade_level ?? null,
                'guardian_name'     => $admission->guardian_name ?? null,
                'guardian_contact'  => $admission->guardian_contact ?? null,
                'mother_name'       => $admission->mother_name ?? null,
                'mother_contact'    => $admission->mother_contact ?? null,
                'father_name'       => $admission->father_name ?? null,
                'father_contact'    => $admission->father_contact ?? null,
                'blood_type'        => $admission->blood_type ?? null,
                'good_moral'        => $admission && $admission->good_moral ? asset($admission->good_moral) : null,
                'form_137'          => $admission && $admission->form_137 ? asset($admission->form_137) : null,
                'form_138'          => $admission && $admission->form_138 ? asset($admission->form_138) : null,
                'birth_certificate' => $admission && $admission->birth_certificate ? asset($admission->birth_certificate) : null,
                'certificate_of_completion' => $admission && $admission->certificate_of_completion ? asset($admission->certificate_of_completion) : null,
            ];
            
        }); 

        return response()->json([
            'isSuccess' => true,
            'message' => 'Enrollments with full admission and exam schedule details.',
            'data' => $data,
            'meta' => [
                'current_page' => $schedules->currentPage(),
                'per_page'     => $schedules->perPage(),
                'total'        => $schedules->total(),
                'last_page'    => $schedules->lastPage(),
            ]
        ]);

    } catch (\Exception $e) {
        return response()->json([
            'isSuccess' => false,
            'message' => 'Failed to retrieve enrollments.',
            'error' => $e->getMessage(),
        ], 500);
    }
}

public function getPassedStudents(Request $request)
{
    try {
        $perPage = $request->input('per_page', 5);
        $page = $request->input('page', 1);

        $query = exam_schedules::with([
            'applicant.academic_program:id,course_name',
            'room:id,room_name',
            'building:id,building_name',
            'campus:id,campus_name'
        ])
        ->where('exam_status', 'passed'); // Only passed students

        // Optional search filter
        if ($request->has('search') && !empty($request->search)) {
            $search = $request->search;
            $query->whereHas('applicant', function ($q) use ($search) {
                $q->where('first_name', 'like', "%$search%")
                  ->orWhere('last_name', 'like', "%$search%")
                  ->orWhere('email', 'like', "%$search%")
                  ->orWhere('contact_number', 'like', "%$search%");
            });
        }

        $query->orderBy('created_at', 'desc');

        $schedules = $query->paginate($perPage, ['*'], 'page', $page);

        $data = $schedules->map(function ($schedule) {
            $admission = $schedule->applicant;

            return [
                // Exam Schedule Info
                'id'              => $schedule->id,
                'test_permit_no'  => $schedule->test_permit_no,
                'exam_date'       => $schedule->exam_date,
                'exam_time_from'  => $schedule->exam_time_from,
                'exam_time_to'    => $schedule->exam_time_to,
                'exam_score'      => $schedule->exam_score,
                'exam_status'     => $schedule->exam_status,
                'room_name'       => optional($schedule->room)->room_name,
                'building_name'   => optional($schedule->building)->building_name,
                'campus_name'     => optional($schedule->campus)->campus_name,

                // Admission Info
                'admission_id'    => $admission->id ?? null,
                'first_name'      => $admission->first_name ?? null,
                'middle_name'     => $admission->middle_name ?? null,
                'last_name'       => $admission->last_name ?? null,
                'suffix'          => $admission->suffix ?? null,
                'full_name'       => trim(($admission->first_name ?? '') . ' ' . ($admission->middle_name ?? '') . ' ' . ($admission->last_name ?? '') . ' ' . ($admission->suffix ?? '')),
                'gender'          => $admission->gender ?? null,
                'birthdate'       => $admission->birthdate ?? null,
                'birthplace'      => $admission->birthplace ?? null,
                'civil_status'    => $admission->civil_status ?? null,
                'email'           => $admission->email ?? null,
                'contact_number'  => $admission->contact_number ?? null,
                'telephone_number'=> $admission->telephone_number ?? null,
                'street_address'  => $admission->street_address ?? null,
                'province'        => $admission->province ?? null,
                'city'            => $admission->city ?? null,
                'barangay'        => $admission->barangay ?? null,
                'nationality'     => $admission->nationality ?? null,
                'religion'        => $admission->religion ?? null,
                'ethnic_affiliation'=> $admission->ethnic_affiliation ?? null,
                'is_4ps_member'   => $admission->is_4ps_member ?? null,
                'is_insurance_member'=> $admission->is_insurance_member ?? null,
                'is_vaccinated'   => $admission->is_vaccinated ?? null,
                'is_indigenous'   => $admission->is_indigenous ?? null,
                'application_type'=> $admission->application_type ?? null,
                'lrn'             => $admission->lrn ?? null,
                'last_school_attended' => $admission->last_school_attended ?? null,
                'remarks'         => $admission->remarks ?? null,
                'grade_level'     => $admission->grade_level ?? null,
                'guardian_name'   => $admission->guardian_name ?? null,
                'guardian_contact'=> $admission->guardian_contact ?? null,
                'mother_name'     => $admission->mother_name ?? null,
                'mother_contact'  => $admission->mother_contact ?? null,
                'father_name'     => $admission->father_name ?? null,
                'father_contact'  => $admission->father_contact ?? null,
                'blood_type'      => $admission->blood_type ?? null,
                'good_moral'      => $admission && $admission->good_moral ? asset($admission->good_moral) : null,
                'form_137'        => $admission && $admission->form_137 ? asset($admission->form_137) : null,
                'form_138'        => $admission && $admission->form_138 ? asset($admission->form_138) : null,
                'birth_certificate'=> $admission && $admission->birth_certificate ? asset($admission->birth_certificate) : null,
                'certificate_of_completion'=> $admission && $admission->certificate_of_completion ? asset($admission->certificate_of_completion) : null,
            ];
        });

        return response()->json([
            'isSuccess' => true,
            'message'   => 'List of students who passed the exam.',
            'data'      => $data,
            'meta'      => [
                'current_page' => $schedules->currentPage(),
                'per_page'     => $schedules->perPage(),
                'total'        => $schedules->total(),
                'last_page'    => $schedules->lastPage(),
            ]
        ]);

    } catch (\Exception $e) {
        return response()->json([
            'isSuccess' => false,
            'message'   => 'Failed to retrieve passed students.',
            'error'     => $e->getMessage(),
        ], 500);
    }
}



    public function getStudentCurriculum($studentId)
{
    try {
        $student = Student::with([
            'enrollment.section.curriculum.subjects'
        ])->find($studentId);

        if (!$student) {
            return response()->json([
                'isSuccess' => false,
                'message' => 'Student not found.'
            ]);
        }

        $curriculumSubjects = optional($student->enrollment->section->curriculum)->subjects ?? collect([]);

        return response()->json([
            'isSuccess' => true,
            'message' => 'Curriculum retrieved successfully.',
            'student_number' => $student->student_number,
            'curriculum_subjects' => $curriculumSubjects->map(function ($subject) {
                return [
                    'id' => $subject->id,
                    'subject_code' => $subject->subject_code,
                    'subject_name' => $subject->subject_name,
                    'units' => $subject->units
                ];
            })
        ]);
    } catch (\Exception $e) {
        return response()->json([
            'isSuccess' => false,
            'message' => 'Failed to retrieve curriculum.',
            'error' => $e->getMessage()
        ]);
    }
}


//TO FIX #1



    public function enrollStudent(Request $request)
    {
        try {
            $validated = $request->validate([
                'student_id' => 'required|integer|exists:exam_schedules,id',
                'misc_fee' => 'required|numeric|min:0',
                'section_id' => 'required|integer|exists:sections,id'
            ]);

            $schedule = exam_schedules::with('applicant.academic_program')->find($validated['student_id']);
            if (!$schedule || !$schedule->applicant) {
                return response()->json([
                    'isSuccess' => false,
                    'message' => 'Exam schedule or applicant not found.',
                ], 404);
            }

            $admission = $schedule->applicant;

            // Check if already enrolled
            $existingStudent = students::where('admission_id', $admission->id)->first();
            if ($existingStudent) {
                return response()->json([
                    'isSuccess' => false,
                    'message' => 'This applicant is already enrolled.',
                ], 400);
            }

            // Generate student number and password
            $lastStudent = students::latest('id')->first();
            $nextId = $lastStudent ? $lastStudent->id + 1 : 1;
            $studentNumber = 'SNL-' . str_pad($nextId, 4, '0', STR_PAD_LEFT);
            $birthdateFormatted = Carbon::parse($admission->birthdate)->format('Ymd');
            $rawPassword = $studentNumber . $birthdateFormatted;
            $hashedPassword = Hash::make($rawPassword);

            $courseId = $admission->academic_program_id ?? null;

            // Calculate total units from curriculum
            $subjectOptions = [];
            $totalUnits = 0;

            $curriculum = DB::table('curriculums')
                ->where('course_id', $courseId)
                ->first();

            if ($curriculum) {
                $subjects = DB::table('curriculum_subject as cs')
                    ->join('subjects as s', 'cs.subject_id', '=', 's.id')
                    ->where('cs.curriculum_id', $curriculum->id)
                    ->select('s.id as subject_id', 's.subject_name', 's.units')
                    ->get();

                foreach ($subjects as $subj) {
                    $subjectOptions[] = [
                        'subject_id' => $subj->subject_id,
                        'subject_name' => $subj->subject_name,
                        'units' => $subj->units,
                    ];
                    $totalUnits += $subj->units;
                }
            }

            // Tuition calculation
            $unitRate   = 200;
            $miscFee    = $validated['misc_fee'];
            $unitsFee   = $totalUnits * $unitRate;
            $tuitionFee = $unitsFee + $miscFee;

            // Save student (no subjects pivot)
            $student = new students();
            $student->admission_id = $admission->id;
            $student->student_number = $studentNumber;
            $student->password = $hashedPassword;
            $student->profile_img = null;
            $student->student_status = 0;
            $student->course_id = $courseId;
            $student->section_id = $validated['section_id'];
            $student->units_fee = $unitsFee;   
            $student->misc_fee = $miscFee;     
            $student->tuition_fee = $tuitionFee; 
            $student->is_active = 1;
            $student->save();

            // Send email
            $html = '
            <html>
            <body style="font-family: Arial, sans-serif;">
                <div style="border:1px solid #ccc; padding:20px; max-width:600px; margin:auto;">
                    <h2>Enrollment Confirmation</h2>
                    <p>Hello <strong>' . $admission->first_name . ' ' . $admission->last_name . '</strong>,</p>
                    <p>You have been successfully enrolled. Here are your login credentials:</p>
                    <ul>
                        <li><strong>Student Number:</strong> ' . $studentNumber . '</li>
                        <li><strong>Password:</strong> ' . $rawPassword . '</li>
                        <li><strong>Tuition Fee:</strong> ₱' . number_format($tuitionFee, 2) . '</li>
                    </ul>
                    <p>Please keep this information secure.</p>
                    <br>
                    <p>Best regards,<br>Enrollment Team</p>
                </div>
            </body>
            </html>
            ';

            Mail::send([], [], function ($message) use ($admission, $html) {
                $message->to($admission->email)
                    ->subject('Your Enrollment Credentials')
                    ->setBody($html, 'text/html');
            });

            return response()->json([
                'isSuccess' => true,
                'message' => 'Student enrolled successfully and credentials sent to email.',
                'student_number' => $studentNumber,
                'units_fee' => $unitsFee,
                'misc_fee' => $miscFee,
                'tuition_fee' => $tuitionFee,
                'total_units' => $totalUnits,
                'subject_options' => $subjectOptions,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'isSuccess' => false,
                'message' => 'Error: ' . $e->getMessage(),
            ], 500);
        }
    }









    public function enrollNow(Request $request)
{
    try {
        // 🔐 Authenticated student
        $student = auth()->user();

        if (!$student) {
            return response()->json([
                'isSuccess' => false,
                'message' => 'Unauthenticated user.'
            ], 401);
        }

        // 🔗 Load student admission record
        $student->load('admission');

        if (!$student->admission) {
            return response()->json([
                'isSuccess' => false,
                'message' => 'Student admission not found.'
            ], 404);
        }

        // ✅ Validate incoming subject_ids (optional)
        $validated = $request->validate([
            'subject_ids' => 'nullable|array',
            'subject_ids.*' => 'exists:subjects,id',
        ]);

        $courseId = $student->admission->academic_program_id;
        $schoolYearId = $student->admission->school_year_id;

        // 📚 Get curriculum by course
        $curriculum = curriculums::where('course_id', $courseId)->first();

        if (!$curriculum) {
            return response()->json([
                'isSuccess' => false,
                'message' => 'Curriculum not found for this course.'
            ], 404);
        }

        // 🧠 Get subject IDs from curriculum
        $curriculumSubjectIds = $curriculum->subjects->pluck('id');

        // 🎯 Decide what subjects to enroll in
        if (!empty($validated['subject_ids'])) {
            $enrolledSubjectIds = collect($validated['subject_ids']);
        } elseif ($curriculumSubjectIds->isNotEmpty()) {
            $enrolledSubjectIds = $curriculumSubjectIds;
        } else {
            return response()->json([
                'isSuccess' => false,
                'message' => 'No subjects found in curriculum and none provided manually.'
            ], 404);
        }

        // 🧩 Find an available section (based on capacity)
        $section = sections::where('course_id', $courseId)
            ->withCount('students')
            ->get()
            ->filter(function ($section) {
                return $section->students_count < $section->max_students;
            })
            ->first();

        if (!$section) {
            return response()->json([
                'isSuccess' => false,
                'message' => 'No section available for this course.'
            ], 404);
        }

        // 🔐 Assign section and update student status
        $student->section_id = $section->id;
        $student->student_status = 1; // Assume 1 = Enrolled
        $student->save();

        // 🔗 Sync subjects
        $student->subjects()->sync($enrolledSubjectIds);

        return response()->json([
            'isSuccess' => true,
            'message' => 'Student enrolled and subjects assigned successfully.',
            'enrolled' => [
                'student_number' => $student->student_number,
                'section' => $section->section_name,
                'subjects' => subjects::whereIn('id', $enrolledSubjectIds)
                    ->get(['id', 'subject_code', 'subject_name']),
            ]
        ]);

    } catch (\Exception $e) {
        return response()->json([
            'isSuccess' => false,
            'message' => 'Enrollment failed.',
            'error' => $e->getMessage()
        ], 500);
    }
}

    
//Curriculum Subjects
    //This function retrieves the subjects in the curriculum for the authenticated student.
    //It checks if the student has an admission record and fetches the curriculum based on the course ID.
    //It returns a JSON response with the subjects or an error message if not found.

    public function getCurriculumSubjects(Request $request)
    {
        $student = auth()->user();

        if (!$student || !$student->admission) {
            return response()->json([
                'isSuccess' => false,
                'message' => 'Student admission not found.'
            ], 404);
        }

        $courseId = $student->admission->academic_program_id;

        $curriculum = curriculums::where('course_id', $courseId)->first();

        if (!$curriculum) {
            return response()->json([
                'isSuccess' => false,
                'message' => 'Curriculum not found for this course.'
            ], 404);
        }

        // 🔄 Get related subjects via pivot
        $subjects = $curriculum->subjects()->get(['subjects.id', 'subject_code', 'subject_name', 'units']);

        return response()->json([
            'isSuccess' => true,
            'curriculum_id' => $curriculum->id,
            'subjects' => $subjects
        ]);
    }


    //This function handles the creation of a new enrollment.
    // public function storeEnrollment(Request $request)
    // {

    //     try {
    //         $user = auth()->user();

    //         $last = enrollments::orderBy('id', 'desc')->first();
    //         $nextId = $last ? $last->id + 1 : 1;
    //         $studentNumber = 'SN-' . now()->format('Y') . '-' . str_pad($nextId, 4, '0', STR_PAD_LEFT);

    //         $validator = Validator::make($request->all(), [
    //             'admission_id' => [
    //                 'nullable',
    //                 Rule::exists('admissions', 'id')->where(function ($query) {
    //                     $query->where('status', 'approved');
    //                 }),
    //             ],
    //             'course_id' => 'required|exists:courses,id',
    //             'section_id' => 'nullable|exists:sections,id',
    //             'is_enrolled' => 'required|boolean',
    //             'is_irregular' => 'boolean',
    //             'date_enrolled' => 'required|date',
    //             'remarks' => 'nullable|string',
    //             'is_archived' => 'boolean'
    //         ]);

    //         if ($validator->fails()) {
    //             return response()->json([
    //                 'isSuccess' => false,
    //                 'message' => 'Validation failed',
    //                 'errors' => $validator->errors()
    //             ], 422);
    //         }

    //         if ($request->admission_id) {
    //             $admission = admissions::findOrFail($request->admission_id);
    //             $schoolYear = $admission->school_year;
    //         } else {
    //             return response()->json([
    //                 'isSuccess' => false,
    //                 'message' => 'Admission ID is required if no school year is provided.',
    //             ], 422);
    //         }

    //         // ✅ Auto sectioning logic if section_id is not provided
    //         $sectionId = $request->section_id;
    //         if (!$sectionId) {
    //             $existingSection = sections::where('course_id', $request->course_id)
    //                 ->where('semester', $request->semester)
    //                 ->where('school_year_id', $admission->school_year_id ?? 1) // fallback if not available
    //                 ->get();

    //             $assigned = false;
    //             foreach ($existingSection as $section) {
    //                 $enrolledCount = enrollments::where('section_id', $section->id)
    //                     ->where('school_year', $admission->school_year)
    //                     ->where('semester', $request->semester)
    //                     ->count();

    //                 if ($enrolledCount < 35) {
    //                     $sectionId = $section->id;
    //                     $assigned = true;
    //                     break;
    //                 }
    //             }


    //             if (!$assigned) {
    //                 $sectionName = 'Section-' . strtoupper(substr(uniqid(), -4));
    //                 $newSection = sections::create([
    //                     'section_name' => $sectionName,
    //                     'course_id' => $request->course_id,
    //                     'school_year_id' => $admission->school_year_id ?? 1,
    //                     'is_archived' => false,
    //                 ]);
    //                 $sectionId = $newSection->id;
    //             }
    //         }

    //         // ✅ Create the enrollment
    //         $enrollment = enrollments::create([
    //             'admission_id' => $request->admission_id,
    //             'course_id' => $request->course_id,
    //             'section_id' => $sectionId,
    //             'school_year' => $schoolYear,
    //             'is_enrolled' => 1,
    //             'is_irregular' => $request->is_irregular ?? false,
    //             'date_enrolled' => $request->date_enrolled,
    //             'remarks' => $request->remarks,
    //             'student_number' => $studentNumber,
    //             'is_archived' => $request->is_archived ?? false,
    //         ]);

    //         return response()->json([
    //             'isSuccess' => true,
    //             'message' => 'Enrollment created successfully',
    //             'data' => $enrollment
    //         ], 201);
    //     } catch (\Throwable $e) {
    //         return response()->json([
    //             'isSuccess' => false,
    //             'message' => 'Enrollment creation failed',
    //             'error' => $e->getMessage()
    //         ], 500);
    //     }
    // }


    

    //HELPERS
    public function getAvailableSubjects()
    {
        $student = auth()->user()->student;

        if (!$student || !$student->admission) {
            return response()->json(['message' => 'Student not found or not admitted.'], 404);
        }

        $courseId = $student->admission->course_id;
        $schoolYearId = $student->admission->school_year_id;
        $semester = $student->admission->semester;

        $subjects = subjects::where('course_id', $courseId)
            ->where('school_year_id', $schoolYearId)
            ->where('semester', $semester)
            ->get();

        return response()->json([
            'isSuccess' => true,
            'data' => $subjects
        ]);
    }
}
