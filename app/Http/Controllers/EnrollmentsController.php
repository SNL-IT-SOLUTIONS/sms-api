<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\enrollments;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Mail;
use App\Models\enrollmentschedule;
use App\Models\students;
use App\Models\exam_schedules;
use App\Models\subjects;
use Illuminate\Support\Facades\Hash;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Barryvdh\DomPDF\Facade\Pdf;
use Maatwebsite\Excel\Validators\ValidationException;


class EnrollmentsController extends Controller
{

    public function saveSchedule(Request $request)
    {
        $validated = $request->validate([
            'school_year_id' => 'required|exists:school_years,id',
            'start_date'     => 'required|date',
            'end_date'       => 'required|date|after_or_equal:start_date',
        ]);

        $schedule = enrollmentschedule::updateOrCreate(
            [
                'school_year_id' => $validated['school_year_id']
            ],
            [
                'start_date' => $validated['start_date'],
                'end_date'   => $validated['end_date'],
                'status'     => 'open'
            ]
        );
        DB::table('students')->update([
            'is_enrolled' => 0,
            'is_assess'   => 0,
        ]);



        return response()->json([
            'isSuccess' => true,
            'message'   => 'Enrollment schedule saved successfully.',
            'data'      => $schedule
        ]);
    }

    // ✅ Get current active schedule
    public function getActiveSchedule()
    {
        $now = now();

        $schedule = enrollmentschedule::where('status', 'open')
            ->whereDate('end_date', '>=', $now)
            ->first();

        if (!$schedule) {
            return response()->json([
                'isSuccess' => false,
                'message'   => 'No active or upcoming enrollment schedule.'
            ], 404);
        }

        return response()->json([
            'isSuccess' => true,
            'data'      => $schedule
        ]);
    }




    public function closeSchedule(Request $request, $id)
    {
        // Validate that the ID exists in the enrollmentschedule table
        $schedule = enrollmentschedule::find($id);

        if (!$schedule) {
            return response()->json([
                'isSuccess' => false,
                'message'   => 'Enrollment schedule not found.'
            ], 404);
        }

        $schedule->status = 'closed';
        $schedule->save();

        return response()->json([
            'isSuccess' => true,
            'message'   => 'Enrollment schedule closed successfully.',
            'data'      => $schedule
        ]);
    }


    public function getExamineesResult(Request $request, $id)
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
            $query->orderBy('exam_score', 'desc')
                ->orderBy('created_at', 'asc');

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
                    'course'            => optional($admission->academic_program)->course_name ?? null,

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
                    'ethnic_affiliation' => $admission->ethnic_affiliation ?? null,
                    'is_4ps_member'     => $admission->is_4ps_member ?? null,
                    'is_insurance_member' => $admission->is_insurance_member ?? null,
                    'is_vaccinated'     => $admission->is_vaccinated ?? null,
                    'is_indigenous'     => $admission->is_indigenous ?? null,
                    'application_type'  => $admission->application_type ?? null,
                    'lrn'               => $admission->lrn ?? null,
                    'last_school_attended' => $admission->last_school_attended ?? null,
                    'remarks'           => $admission->remarks ?? null,
                    'grade_level'       => $admission->grade_level_id ?? null,
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

    //PASS STUDENTS
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
                ->where('exam_status', 'passed')
                ->where('is_approved', 0);

            // ✅ Search filter
            if ($request->has('search') && !empty($request->search)) {
                $search = $request->search;

                $query->where(function ($q) use ($search) {
                    // Search on exam_schedules table
                    $q->where('test_permit_no', 'like', "%$search%");

                    // Search on applicant relation
                    $q->orWhereHas('applicant', function ($q2) use ($search) {
                        $q2->where('first_name', 'like', "%$search%")
                            ->orWhere('last_name', 'like', "%$search%")
                            ->orWhere('email', 'like', "%$search%")
                            ->orWhere('contact_number', 'like', "%$search%");
                    });
                });
            }

            // ✅ Filter (example: by course_id, campus_id, exam_date, etc.)
            if ($request->filled('course_id')) {
                $query->whereHas('applicant', function ($q) use ($request) {
                    $q->where('academic_program_id', $request->course_id);
                });
            }

            if ($request->filled('campus_id')) {
                $query->whereHas('applicant', function ($q) use ($request) {
                    $q->where('campus_id', $request->campus_id);
                });
            }


            if ($request->filled('academic_year_id')) {
                $query->where('academic_year_id', $request->academic_year_id);
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
                    'is_approved'     => $schedule->is_approved,
                    'room_name'       => optional($schedule->room)->room_name,
                    'building_name'   => optional($schedule->building)->building_name,
                    'has_form137'     => $schedule->has_form137 ?? null,
                    'has_form138'     => $schedule->has_form138 ?? null,
                    'has_good_moral'  => $schedule->has_good_moral ?? null,
                    'has_certificate_of_completion' => $schedule->has_certificate_of_completion ?? null,
                    'has_birth_certificate' => $schedule->has_birth_certificate ?? null,

                    // Admission Info (guarded)
                    'academic_year_id'          => $admission->academic_year_id ?? null,
                    'course_id'          => $admission->academic_program_id ?? null,
                    'course'          => optional($admission->academic_program)->course_name ?? null,
                    'course_code'     => optional($admission->course)->course_code ?? null,
                    'campus_name'     => optional(optional($admission)->campus)->campus_name,
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
                    'telephone_number' => $admission->telephone_number ?? null,
                    'street_address'  => $admission->street_address ?? null,
                    'province'        => $admission->province ?? null,
                    'city'            => $admission->city ?? null,
                    'barangay'        => $admission->barangay ?? null,
                    'nationality'     => $admission->nationality ?? null,
                    'religion'        => $admission->religion ?? null,
                    'ethnic_affiliation' => $admission->ethnic_affiliation ?? null,
                    'is_4ps_member'   => $admission->is_4ps_member ?? null,
                    'is_insurance_member' => $admission->is_insurance_member ?? null,
                    'is_vaccinated'   => $admission->is_vaccinated ?? null,
                    'is_indigenous'   => $admission->is_indigenous ?? null,
                    'application_type' => $admission->application_type ?? null,
                    'lrn'             => $admission->lrn ?? null,
                    'last_school_attended' => $admission->last_school_attended ?? null,
                    'remarks'         => $admission->remarks ?? null,
                    'grade_level'     => $admission->grade_level_id ?? null,
                    'guardian_name'   => $admission->guardian_name ?? null,
                    'guardian_contact' => $admission->guardian_contact ?? null,
                    'mother_name'     => $admission->mother_name ?? null,
                    'mother_contact'  => $admission->mother_contact ?? null,
                    'father_name'     => $admission->father_name ?? null,
                    'father_contact'  => $admission->father_contact ?? null,
                    'blood_type'      => $admission->blood_type ?? null,
                    'good_moral'      => $admission && $admission->good_moral ? asset($admission->good_moral) : null,
                    'form_137'        => $admission && $admission->form_137 ? asset($admission->form_137) : null,
                    'form_138'        => $admission && $admission->form_138 ? asset($admission->form_138) : null,
                    'birth_certificate' => $admission && $admission->birth_certificate ? asset($admission->birth_certificate) : null,
                    'certificate_of_completion' => $admission && $admission->certificate_of_completion ? asset($admission->certificate_of_completion) : null,

                    'is_enrolled'     => optional($admission->student)->is_enrolled ?? null,
                ];
            });

            return response()->json([
                'isSuccess' => true,
                'message'   => 'List of students who passed but not yet approved.',
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
                'message'   => 'Failed to retrieve unapproved passed students.',
                'error'     => $e->getMessage(),
            ], 500);
        }
    }



    public function getallStudents(Request $request)
    {
        try {
            $perPage = $request->input('per_page', 5);
            $page = $request->input('page', 1);

            $query = exam_schedules::with([
                'applicant.academic_program:id,course_name',
                'room:id,room_name',
                'building:id,building_name',
                'campus:id,campus_name'
            ]);

            // ✅ Search filter
            if ($request->has('search') && !empty($request->search)) {
                $search = $request->search;

                $query->where(function ($q) use ($search) {
                    // Search on exam_schedules table
                    $q->where('test_permit_no', 'like', "%$search%");

                    // Search on applicant relation
                    $q->orWhereHas('applicant', function ($q2) use ($search) {
                        $q2->where('first_name', 'like', "%$search%")
                            ->orWhere('last_name', 'like', "%$search%")
                            ->orWhere('email', 'like', "%$search%")
                            ->orWhere('contact_number', 'like', "%$search%");
                    });
                });
            }
            // ✅ Filter options
            if ($request->filled('course_id')) {
                $query->whereHas('applicant', function ($q) use ($request) {
                    $q->where('academic_program_id', $request->course_id);
                });
            }

            if ($request->filled('campus_id')) {
                $query->whereHas('applicant', function ($q) use ($request) {
                    $q->where('campus_id', $request->campus_id);
                });
            }

            if ($request->filled('academic_year_id')) {
                $query->where('academic_year_id', $request->academic_year_id);
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
                    'is_approved'     => $schedule->is_approved,
                    'room_name'       => optional($schedule->room)->room_name,
                    'building_name'   => optional($schedule->building)->building_name,
                    'has_form137'     => $schedule->has_form137 ?? null,
                    'has_form138'     => $schedule->has_form138 ?? null,
                    'has_good_moral'  => $schedule->has_good_moral ?? null,
                    'has_certificate_of_completion' => $schedule->has_certificate_of_completion ?? null,
                    'has_birth_certificate' => $schedule->has_birth_certificate ?? null,

                    // Admission Info (guarded)
                    'academic_year_id'          => $admission->academic_year_id ?? null,
                    'course_id'          => $admission->academic_program_id ?? null,
                    'course'          => optional($admission->academic_program)->course_name ?? null,
                    'course_code'     => optional($admission->course)->course_code ?? null,
                    'campus_name'     => optional(optional($admission)->campus)->campus_name,
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
                    'telephone_number' => $admission->telephone_number ?? null,
                    'street_address'  => $admission->street_address ?? null,
                    'province'        => $admission->province ?? null,
                    'city'            => $admission->city ?? null,
                    'barangay'        => $admission->barangay ?? null,
                    'nationality'     => $admission->nationality ?? null,
                    'religion'        => $admission->religion ?? null,
                    'ethnic_affiliation' => $admission->ethnic_affiliation ?? null,
                    'is_4ps_member'   => $admission->is_4ps_member ?? null,
                    'is_insurance_member' => $admission->is_insurance_member ?? null,
                    'is_vaccinated'   => $admission->is_vaccinated ?? null,
                    'is_indigenous'   => $admission->is_indigenous ?? null,
                    'application_type' => $admission->application_type ?? null,
                    'lrn'             => $admission->lrn ?? null,
                    'last_school_attended' => $admission->last_school_attended ?? null,
                    'remarks'         => $admission->remarks ?? null,
                    'grade_level'     => $admission->grade_level_id ?? null,
                    'guardian_name'   => $admission->guardian_name ?? null,
                    'guardian_contact' => $admission->guardian_contact ?? null,
                    'mother_name'     => $admission->mother_name ?? null,
                    'mother_contact'  => $admission->mother_contact ?? null,
                    'father_name'     => $admission->father_name ?? null,
                    'father_contact'  => $admission->father_contact ?? null,
                    'blood_type'      => $admission->blood_type ?? null,
                    'good_moral'      => $admission && $admission->good_moral ? asset($admission->good_moral) : null,
                    'form_137'        => $admission && $admission->form_137 ? asset($admission->form_137) : null,
                    'form_138'        => $admission && $admission->form_138 ? asset($admission->form_138) : null,
                    'birth_certificate' => $admission && $admission->birth_certificate ? asset($admission->birth_certificate) : null,
                    'certificate_of_completion' => $admission && $admission->certificate_of_completion ? asset($admission->certificate_of_completion) : null,

                    'is_enrolled'     => optional($admission->student)->is_enrolled ?? null,
                ];
            });

            return response()->json([
                'isSuccess' => true,
                'message'   => 'List of students who passed but not yet approved.',
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
                'message'   => 'Failed to retrieve unapproved passed students.',
                'error'     => $e->getMessage(),
            ], 500);
        }
    }




    //RECONSIDERED STUDENTS
    public function getReconsideredStudents(Request $request)
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
                ->where('exam_status', 'reconsider')
                ->where('is_approved', 0);

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
                    'is_approved'     => $schedule->is_approved,
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
                    'telephone_number' => $admission->telephone_number ?? null,
                    'street_address'  => $admission->street_address ?? null,
                    'province'        => $admission->province ?? null,
                    'city'            => $admission->city ?? null,
                    'barangay'        => $admission->barangay ?? null,
                    'nationality'     => $admission->nationality ?? null,
                    'religion'        => $admission->religion ?? null,
                    'ethnic_affiliation' => $admission->ethnic_affiliation ?? null,
                    'is_4ps_member'   => $admission->is_4ps_member ?? null,
                    'is_insurance_member' => $admission->is_insurance_member ?? null,
                    'is_vaccinated'   => $admission->is_vaccinated ?? null,
                    'is_indigenous'   => $admission->is_indigenous ?? null,
                    'application_type' => $admission->application_type ?? null,
                    'lrn'             => $admission->lrn ?? null,
                    'last_school_attended' => $admission->last_school_attended ?? null,
                    'remarks'         => $admission->remarks ?? null,
                    'grade_level'     => $admission->grade_level_id ?? null,
                    'guardian_name'   => $admission->guardian_name ?? null,
                    'guardian_contact' => $admission->guardian_contact ?? null,
                    'mother_name'     => $admission->mother_name ?? null,
                    'mother_contact'  => $admission->mother_contact ?? null,
                    'father_name'     => $admission->father_name ?? null,
                    'father_contact'  => $admission->father_contact ?? null,
                    'blood_type'      => $admission->blood_type ?? null,
                    'good_moral'      => $admission && $admission->good_moral ? asset($admission->good_moral) : null,
                    'form_137'        => $admission && $admission->form_137 ? asset($admission->form_137) : null,
                    'form_138'        => $admission && $admission->form_138 ? asset($admission->form_138) : null,
                    'birth_certificate' => $admission && $admission->birth_certificate ? asset($admission->birth_certificate) : null,
                    'certificate_of_completion' => $admission && $admission->certificate_of_completion ? asset($admission->certificate_of_completion) : null,
                ];
            });

            return response()->json([
                'isSuccess' => true,
                'message'   => 'List of students marked for reconsideration.',
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
                'message'   => 'Failed to retrieve reconsidered students.',
                'error'     => $e->getMessage(),
            ], 500);
        }
    }

    // Mark student as passed
    public function markAsPassed($id)
    {
        try {
            $user = Auth::user();
            $schedule = exam_schedules::with('applicant')->findOrFail($id);

            if ($schedule->exam_status !== 'reconsider') {
                return response()->json([
                    'isSuccess' => false,
                    'message'   => 'This student is not marked for reconsideration.',
                ], 400);
            }

            $schedule->update([
                'exam_status' => 'passed',
            ]);

            // Send email to applicant
            if ($schedule->applicant && $schedule->applicant->email) {
                $this->sendPassedEmail($schedule->applicant);
            }

            return response()->json([
                'isSuccess' => true,
                'message'   => 'Student status changed to passed successfully.',
                'data'      => $schedule
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'isSuccess' => false,
                'message'   => 'Error: ' . $e->getMessage(),
            ], 500);
        }
    }

    protected function sendPassedEmail($applicant, $score = null)
    {
        $studentName = trim("{$applicant->first_name} {$applicant->last_name}");
        $to = $applicant->email;

        $subject = "SNL Examination Result - Congratulations!";

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

        Mail::send([], [], function ($message) use ($to, $subject, $htmlContent) {
            $message->to($to)
                ->subject($subject)
                ->setBody($htmlContent, 'text/html');
        });
    }
    //

    public function enrollStudent(Request $request)
    {
        try {
            $registrar = auth()->user();

            if (!$registrar) {
                return response()->json([
                    'isSuccess' => false,
                    'message'   => 'Unauthorized. Only registrar can perform this action.'
                ], 403);
            }

            // 🔽 Registrar specifies which student to enroll (via exam_schedules.id)
            $validated = $request->validate([
                'student_id'     => 'required|exists:exam_schedules,id',
                'subjects'       => 'required|array|min:1',
                'subjects.*'     => 'exists:subjects,id',
                'school_year_id' => 'required|exists:school_years,id',
                'grade_level_id' => 'nullable|exists:grade_levels,id'
            ]);

            $examSchedule = DB::table('exam_schedules')->where('id', $validated['student_id'])->first();

            if (!$examSchedule) {
                return response()->json([
                    'isSuccess' => false,
                    'message'   => 'Exam schedule record not found.'
                ], 404);
            }

            // 🔽 Find or create student linked to this exam schedule
            $student = students::where('exam_schedules_id', $examSchedule->id)->first();

            if (!$student) {
                $student = students::create([
                    'exam_schedules_id' => $examSchedule->id,
                    'admission_id'      => $examSchedule->admission_id ?? null,
                    'grade_level_id'    => $validated['grade_level_id'] ?? null,
                    'academic_year_id'  => $validated['school_year_id'],
                    'is_assess'         => 0,
                    'is_enrolled'       => 0,
                ]);
            }

            $schoolYearId = $validated['school_year_id'];

            // 🔽 Still check for unpaid enrollments unless registrar wants to override
            $hasUnpaid = enrollments::where('student_id', $student->id)
                ->where('payment_status', 'Unpaid')
                ->exists();

            if ($hasUnpaid) {
                return response()->json([
                    'isSuccess' => false,
                    'message'   => 'This student has unpaid enrollment(s). Please settle first or override manually.'
                ], 400);
            }

            // 🔽 Curriculum
            $curriculum = DB::table('curriculums')
                ->where('course_id', $examSchedule->course_id ?? $student->course_id)
                ->orderBy('created_at', 'desc')
                ->first();

            if (!$curriculum) {
                return response()->json([
                    'isSuccess' => false,
                    'message'   => 'No curriculum found for this course.'
                ], 400);
            }

            // 🔽 Fee computation
            $subjects = subjects::whereIn('id', $validated['subjects'])->get();
            $totalUnits = $subjects->sum('units');
            $unitRate   = 200;
            $unitsFee   = $totalUnits * $unitRate;
            $tuitionFee = $unitsFee;

            $miscFees = DB::table('fees')
                ->where('is_active', 1)
                ->where('is_archived', 0)
                ->where('school_year_id', $schoolYearId)
                ->get();

            $miscFeeTotal = $miscFees->sum('default_amount');
            $totalFee     = $tuitionFee + $miscFeeTotal;

            // 🔽 Generate reference number
            do {
                $referenceNumber = mt_rand(1000000, 9999999);
            } while (enrollments::where('reference_number', $referenceNumber)->exists());

            // 🔽 Enrollment
            $enrollment = enrollments::create([
                'student_id'           => $student->id,
                'school_year_id'       => $schoolYearId,
                'grade_level_id'       => $validated['grade_level_id'] ?? $student->grade_level_id,
                'tuition_fee'          => $tuitionFee,
                'misc_fee'             => $miscFeeTotal,
                'original_tuition_fee' => $totalFee,
                'total_tuition_fee'    => $totalFee,
                'payment_status'       => 'Unpaid',
                'transaction'          => 'Enrollment',
                'reference_number'     => $referenceNumber,
                'created_by'           => $registrar->id
            ]);

            foreach ($miscFees as $fee) {
                DB::table('enrollment_fees')->insert([
                    'enrollment_id' => $enrollment->id,
                    'fee_id'        => $fee->id,
                    'amount'        => $fee->default_amount,
                    'created_at'    => now(),
                    'updated_at'    => now(),
                ]);
            }

            // 🔽 Sync subjects
            $pivotData = [];
            foreach ($validated['subjects'] as $subjectId) {
                $pivotData[$subjectId] = ['school_year_id' => $schoolYearId];
            }
            $student->subjects()->syncWithoutDetaching($pivotData);

            // 🔽 Update student
            $student->update([
                'curriculum_id'     => $curriculum->id,
                'academic_year_id'  => $schoolYearId,
                'is_assess'         => 1,
                'is_enrolled'       => 1
            ]);

            return response()->json([
                'isSuccess' => true,
                'message'   => 'Student successfully enrolled.',
                'data'      => [
                    'enrollment'       => $enrollment,
                    'subjects'         => $subjects,
                    'curriculum_id'    => $curriculum->id,
                    'total_units'      => $totalUnits,
                    'misc_fees'        => $miscFees,
                    'total_amount'     => $totalFee,
                    'reference_number' => $referenceNumber
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'isSuccess' => false,
                'message'   => $e->getMessage()
            ], 500);
        }
    }




    public function getStudentCurriculums($id)
    {
        try {
            $student = students::find($id);

            if (!$student) {
                return response()->json([
                    'isSuccess' => false,
                    'message' => 'Student not found.'
                ], 404);
            }

            $curriculums = DB::table('curriculums')
                ->join('courses', 'curriculums.course_id', '=', 'courses.id')
                ->where('curriculums.course_id', $student->course_id) // align with student's course
                ->select(
                    'curriculums.id',
                    'curriculums.curriculum_name',
                    'courses.course_name'
                )
                ->get();

            return response()->json([
                'isSuccess' => true,
                'message' => 'Curriculums retrieved successfully.',
                'student_number' => $student->student_number,
                'course' => $student->course_id,
                'curriculums' => $curriculums->map(function ($c) {
                    return [
                        'id'   => $c->id,
                        'name' => $c->curriculum_name . ' (' . $c->course_name . ')'
                    ];
                })
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'isSuccess' => false,
                'message' => 'Failed to retrieve curriculums.',
                'error' => $e->getMessage()
            ]);
        }
    }




    //1st step REGISTRAR
    public function approveStudent(Request $request)
    {
        $user = Auth::user();
        try {
            $validated = $request->validate([
                'id'   => 'required|integer|exists:exam_schedules,id',
                'has_form137'  => 'required|boolean',
                'has_form138'  => 'required|boolean',
                'has_birth_certificate' => 'required|boolean',
                'has_good_moral' => 'required|boolean',
                'has_certificate_of_completion' => 'required|boolean',
            ]);

            $schedule = exam_schedules::with(['applicant'])->find($validated['id']);

            if (!$schedule || !$schedule->applicant) {
                return response()->json([
                    'isSuccess' => false,
                    'message'   => 'Exam schedule or applicant not found.',
                ], 404);
            }

            $admission = $schedule->applicant;

            if (students::where('exam_schedules_id', $schedule->id)->exists()) {
                return response()->json([
                    'isSuccess' => false,
                    'message'   => 'This applicant is already enrolled.',
                ], 400);
            }

            // Generate student number + password
            $lastStudent   = students::latest('id')->first();
            $nextId        = $lastStudent ? $lastStudent->id + 1 : 1;
            $studentNumber = 'SNL-' . str_pad($nextId, 4, '0', STR_PAD_LEFT);

            $birthdateFormatted = Carbon::parse($admission->birthdate)->format('Ymd');
            $rawPassword        = $studentNumber . $birthdateFormatted;
            $hashedPassword     = Hash::make($rawPassword);

            // Assign section base on course
            $sections = DB::table('sections')
                ->where('campus_id', $admission->school_campus_id)
                ->where('course_id', $admission->academic_program_id) //match course
                ->where('is_archived', 0)
                ->orderBy('id')
                ->get();

            $section = null;
            foreach ($sections as $sec) {
                $currentCount = DB::table('students')
                    ->where('section_id', $sec->id)
                    ->count();
                if ($currentCount < $sec->students_size) {
                    $section = $sec;
                    break;
                }
            }

            $curriculum = DB::table('curriculums')
                ->where('course_id', $admission->academic_program_id)
                ->orderBy('created_at', 'desc')
                ->first();

            if (!$curriculum) {
                return response()->json([
                    'isSuccess' => false,
                    'message'   => 'No curriculum assigned for this course.'
                ], 400);
            }

            if (!$section) {
                return response()->json([
                    'isSuccess' => false,
                    'message'   => 'No available section with space for this campus.',
                ], 400);
            }

            // Check if student has complete documents
            $hasAllDocs = $validated['has_form137']
                && $validated['has_form138']
                && $validated['has_good_moral']
                && $validated['has_certificate_of_completion']
                && $validated['has_birth_certificate'];

            $enrollmentStatus = $hasAllDocs ? 'Complete Requirements' : 'Incomplete Requirements';
            $schedule->update(['is_approved' => 1]);

            // Create student record
            $student = students::create([
                'admission_id'                  => $schedule->admission_id,
                'exam_schedules_id'             => $schedule->id,
                'student_number'                => $studentNumber,
                'password'                      => $hashedPassword,
                'profile_img'                   => null,
                'student_status'                => 0,
                'course_id'                     => $admission->academic_program_id,
                'section_id'                    => $section->id,
                'academic_year_id'              => $admission->academic_year_id,
                'grade_level_id'                => $admission->grade_level_id ?? null,
                'curriculum_id'                 => $curriculum->id, // ✅ added here
                'user_type'                     => 'student',
                'is_active'                     => 1,
                'enrollment_status'             => $enrollmentStatus,
                'has_form137'                   => $validated['has_form137'],
                'has_form138'                   => $validated['has_form138'],
                'has_good_moral'                => $validated['has_good_moral'],
                'has_certificate_of_completion' => $validated['has_certificate_of_completion'],
                'has_birth_certificate'         => $validated['has_birth_certificate'],
            ]);

            // Send email
            $html = '<html><body style="font-family: Arial, sans-serif;">
                <h2>Enrollment Confirmation</h2>
                <p>Hello <strong>' . $admission->first_name . ' ' . $admission->last_name . '</strong>,</p>
                <p>You have been successfully enrolled. Here are your login credentials:</p>
                <ul>
                    <li><strong>Student Number:</strong> ' . $studentNumber . '</li>
                    <li><strong>Password:</strong> ' . $rawPassword . '</li>
                    <li><strong>Enrollment Status:</strong> ' . $enrollmentStatus . '</li>
                </ul>
            </body></html>';

            Mail::send([], [], function ($message) use ($admission, $html) {
                $message->to($admission->email)
                    ->subject('Your Enrollment Credentials')
                    ->setBody($html, 'text/html');
            });

            return response()->json([
                'isSuccess'      => true,
                'message'        => 'Student enrolled successfully. Credentials sent via email.',
                'student_id'     => $student->id,
                'student_number' => $studentNumber,
                'section_id'     => $section->id,
                'enrollment_status' => $enrollmentStatus,
            ], 200);
        } catch (ValidationException $e) {
            return response()->json([
                'isSuccess' => false,
                'message'   => 'Validation failed',
                'errors'    => $e->errors(), // 👈 shows the exact problem field
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'isSuccess' => false,
                'message'   => 'Error: ' . $e->getMessage(),
            ], 500);
        }
    }




    public function updateStudentDocs(Request $request, $id)
    {
        $validated = $request->validate([
            'has_form137'  => 'required|boolean',
            'has_form138'  => 'required|boolean',
            'has_birth_certificate' => 'required|boolean',
            'has_good_moral' => 'required|boolean',
            'has_certificate_of_completion' => 'required|boolean',
        ]);

        $student = students::findOrFail($id);

        $hasAllDocs = $validated['has_form137']
            && $validated['has_form138']
            && $validated['has_good_moral']
            && $validated['has_certificate_of_completion']
            && $validated['has_birth_certificate'];

        $student->update([
            ...$validated,
            'enrollment_status' => $hasAllDocs ? 'Complete Requirements' : 'Incomeplete Requirements',
        ]);

        return response()->json([
            'isSuccess' => true,
            'message'   => 'Student documents updated successfully.',
            'student'   => $student
        ]);
    }


    //UPDATED 9/4/2025
    public function getProcessPayments(Request $request)
    {
        try {
            $perPage = $request->get('per_page', 5);
            $page    = $request->get('page', 1);

            // Base query with relationships
            $query = students::with([
                'admission.gradeLevel',
                'admission.course',
                'admission.campus',
                'section',
                'payments',
                'enrollment.fees'
            ])
                ->whereHas('enrollments', function ($q) {
                    $q->where('total_tuition_fee', '>', 0);
                })
                ->orderBy('created_at', 'desc');

            // 🔍 Search
            if ($request->has('search')) {
                $search = $request->search;
                $query->where(function ($q) use ($search) {
                    $q->where('student_number', 'like', "%$search%")
                        ->orWhereHas('admission', function ($q2) use ($search) {
                            $q2->where('first_name', 'like', "%$search%")
                                ->orWhere('last_name', 'like', "%$search%");
                        });
                });
            }

            // 🎯 Filters
            if ($request->has('campus')) {
                $query->whereHas('admission.campus', function ($q) use ($request) {
                    $q->where('campus_name', $request->campus);
                });
            }
            if ($request->has('course')) {
                $query->whereHas('admission.course', function ($q) use ($request) {
                    $q->where('course_name', $request->course);
                });
            }
            if ($request->has('section')) {
                $query->whereHas('section', function ($q) use ($request) {
                    $q->where('section_name', $request->section);
                });
            }
            if ($request->has('status')) {
                $query->where('enrollment_status', $request->status);
            }
            if ($request->has('is_active')) {
                $query->where('is_active', $request->is_active);
            }
            if ($request->has('academic_year_id')) {
                $query->where('academic_year_id', $request->academic_year_id);
            }

            // 📄 Paginate
            $students = $query->paginate($perPage, ['*'], 'page', $page);
            $results = [];

            foreach ($students as $student) {
                $admission = $student->admission;

                // 🎓 Enrollment (latest by academic year)
                $enrollment = enrollments::with('fees')
                    ->where('student_id', $student->id)
                    ->orderBy('created_at', 'desc')
                    ->first();


                // 🏫 Academic year
                $academicYear = null;
                if ($student->academic_year_id) {
                    $academicYear = DB::table('school_years')
                        ->where('id', $student->academic_year_id)
                        ->select('id', 'school_year', 'semester', 'is_active', 'is_archived')
                        ->first();
                }

                // 💰 Payments for that student (and academic year if available)
                $paymentsQuery = DB::table('payments')
                    ->where('student_id', $student->id);

                if ($student->academic_year_id) {
                    $paymentsQuery->where('school_year_id', $student->academic_year_id);
                }

                $payments = $paymentsQuery->get();
                $totalPaid = $payments->sum('paid_amount');

                $tuitionFee = $enrollment?->tuition_fee ?? 0;

                // 🧾 Breakdown misc fees
                $miscBreakdown = [];
                $miscFee = 0;
                if ($enrollment) {
                    $miscBreakdown = $enrollment->fees->map(function ($fee) {
                        return [
                            'fee_id'   => $fee->id,
                            'fee_name' => $fee->fee_name,
                            'amount'   => $fee->pivot->amount,
                        ];
                    });
                    $miscFee = $enrollment->fees->sum(fn($f) => $f->pivot->amount);

                    $miscFee = $miscBreakdown->sum('amount');
                }

                $totalAmount = $enrollment?->total_tuition_fee ?? ($tuitionFee + $miscFee);
                $outstandingBalance = max($totalAmount - $totalPaid, 0);

                // 📚 Curriculum & subjects
                $curriculum = null;
                $groupedSubjects = [];
                $totalUnits = 0;

                if ($student->curriculum_id) {
                    $curriculum = DB::table('curriculums')->where('id', $student->curriculum_id)->first();
                    if ($curriculum) {
                        $subjects = DB::table('student_subjects as ss')
                            ->join('subjects as s', 'ss.subject_id', '=', 's.id')
                            ->join('curriculum_subject as cs', 'cs.subject_id', '=', 's.id')
                            ->join('school_years as sy', 'ss.school_year_id', '=', 'sy.id')
                            ->where('ss.student_id', $student->id)
                            ->where('cs.curriculum_id', $curriculum->id)
                            ->select(
                                's.id as subject_id',
                                's.subject_name',
                                's.units',
                                's.grade_level_id',
                                'sy.semester'
                            )
                            ->get();

                        foreach ($subjects as $subj) {
                            $key = "Level {$subj->grade_level_id} - {$subj->semester}";
                            $groupedSubjects[$key][] = [
                                'subject_id'   => $subj->subject_id,
                                'subject_name' => $subj->subject_name,
                                'units'        => $subj->units,
                            ];
                            $totalUnits += $subj->units;
                        }
                    }
                }

                $results[] = [
                    'id'                  => $student->id,
                    'student_number'      => $student->student_number,
                    'status'              => $student->enrollment_status,
                    'payment_status'      => $enrollment?->payment_status ?? $student->payment_status,
                    'grade_level'         => $admission?->gradeLevel?->grade_level,
                    'course'              => $admission?->course?->course_name,
                    'campus'              => $admission?->campus?->campus_name,
                    'tuition_fee'         => $tuitionFee,
                    'misc_fee_total'      => $miscFee,
                    'misc_fee_breakdown'  => $miscBreakdown,
                    'total_amount'        => $totalAmount,
                    'total_paid'          => $totalPaid,
                    'outstanding_balance' => $outstandingBalance,
                    'is_active'           => $student->is_active,
                    'academic_year'       => $academicYear,
                    'applicant' => [
                        'applicant_id' => $admission?->id,
                        'first_name'   => $admission?->first_name,
                        'last_name'    => $admission?->last_name,
                        'email'        => $admission?->email,
                        'contact'      => $admission?->contact_number,
                    ],
                    'section' => [
                        'section_id'   => $student->section?->id,
                        'section_name' => $student->section?->section_name,
                    ],
                    'curriculum' => $curriculum ? [
                        'id'          => $curriculum->id,
                        'name'        => $curriculum->curriculum_name,
                        'description' => $curriculum->curriculum_description,
                    ] : null,
                    'subjects_by_semester' => $groupedSubjects,
                    'total_units'          => $totalUnits,
                ];
            }

            return response()->json([
                'isSuccess'  => true,
                'data'       => $results,
                'pagination' => [
                    'total'        => $students->total(),
                    'per_page'     => $students->perPage(),
                    'current_page' => $students->currentPage(),
                    'last_page'    => $students->lastPage(),
                ],
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'isSuccess' => false,
                'message'   => 'Error: ' . $e->getMessage(),
            ], 500);
        }
    }









    //UPDATED 9/4/2025
    public function sendReceipt(Request $request, $id)
    {
        try {
            auth()->user();

            // 🔍 Student with relations
            $student = students::with([
                'admission.course',
                'admission.campus',
                'payments',
                'subjects'
            ])->findOrFail($id);

            $studentNumber = $student->student_number;
            $courseName    = $student->admission?->course?->course_name ?? '—';
            $campusName    = $student->admission?->campus?->campus_name ?? '—';
            $firstName     = $student->admission?->first_name ?? '';
            $lastName      = $student->admission?->last_name ?? '';
            $email         = $student->admission?->email;

            if (!$email) {
                return response()->json([
                    'isSuccess' => false,
                    'message'   => 'No email found for this student.',
                ], 422);
            }

            // 🎓 Get latest enrollment (per academic year if needed)
            $enrollment = DB::table('enrollments')
                ->where('student_id', $student->id)
                ->orderBy('created_at', 'desc')
                ->first();

            $tuitionFee = (float) ($enrollment?->tuition_fee ?? 0);
            $miscFee    = (float) ($enrollment?->misc_fee ?? 0);
            $totalFee   = (float) ($enrollment?->total_tuition_fee ?? ($tuitionFee + $miscFee));

            // 📚 Total units from subjects
            $totalUnits = (int) ($student->subjects()->sum('units') ?? 0);

            // 💰 Payments for this student
            $paymentsQuery = DB::table('payments')->where('student_id', $student->id);
            if ($enrollment?->school_year_id) {
                $paymentsQuery->where('school_year_id', $enrollment->school_year_id);
            }
            $payments = $paymentsQuery->get();

            $latestPayment      = $payments->sortByDesc('created_at')->first();
            $totalPaid          = $payments->sum('paid_amount');
            $outstandingBalance = max($totalFee - $totalPaid, 0);

            $subjects = $student->subjects()->get(['subject_code', 'subject_name', 'units']);

            // 📄 Ensure receipts folder exists
            $receiptDir = storage_path('app/receipts');
            if (!file_exists($receiptDir)) {
                mkdir($receiptDir, 0777, true);
            }

            $pdfPath = $receiptDir . "/receipt_{$studentNumber}.pdf";

            // 🧾 Generate PDF
            $pdf = Pdf::loadView('pdf.receipt', [
                'studentNumber'    => $studentNumber,
                'courseName'       => $courseName,
                'campusName'       => $campusName,
                'tuitionFee'       => number_format($tuitionFee, 2),
                'miscFee'          => number_format($miscFee, 2),
                'totalUnits'       => $totalUnits,
                'firstName'        => $firstName,
                'lastName'         => $lastName,
                'subjects'         => $subjects,

                // 🔑 Payment details
                'receiptNo'        => $latestPayment->receipt_no ?? 'N/A',
                'paidAt'           => $latestPayment->paid_at ?? now(),
                'paidAmount'       => number_format($totalPaid, 2),
                'remainingBalance' => number_format($outstandingBalance, 2),
            ]);
            $pdf->save($pdfPath);

            // 📧 Email PDF
            Mail::send([], [], function ($message) use ($email, $studentNumber, $pdfPath) {
                $message->to($email)
                    ->subject("Statement of Account - {$studentNumber}")
                    ->attach($pdfPath, [
                        'as'   => "Statement_{$studentNumber}.pdf",
                        'mime' => 'application/pdf',
                    ])
                    ->setBody('Please see attached Statement of Account (PDF).', 'text/html');
            });

            return response()->json([
                'isSuccess' => true,
                'message'   => 'Receipt generated and emailed successfully.',
                'pdf_url'   => url("storage/receipts/receipt_{$studentNumber}.pdf")
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'isSuccess' => false,
                'message'   => 'Error: ' . $e->getMessage(),
            ], 500);
        }
    }







    //UPDATED 9/4/2025 //MANAGE ENROLLMENT FRONTEND
    public function getAllEnrollments(Request $request)
    {
        try {
            $perPage = $request->get('per_page', 5);
            $page    = $request->get('page', 1);

            // Base query with relationships
            $query = students::with([
                'admission.gradeLevel',
                'admission.course',
                'admission.campus',
                'section',
                'curriculum'
            ])
                ->orderBy('created_at', 'desc')
                ->where('is_enrolled', 0);

            // 🔎 Search
            if ($request->has('search')) {
                $search = $request->search;
                $query->where(function ($q) use ($search) {
                    $q->where('student_number', 'like', "%$search%")
                        ->orWhereHas('admission', function ($q2) use ($search) {
                            $q2->where('first_name', 'like', "%$search%")
                                ->orWhere('last_name', 'like', "%$search%");
                        });
                });
            }

            // 🎯 Filters
            if ($request->has('campus')) {
                $query->whereHas('admission.campus', function ($q) use ($request) {
                    $q->where('campus_name', $request->campus);
                });
            }
            if ($request->has('course')) {
                $query->whereHas('admission.course', function ($q) use ($request) {
                    $q->where('course_name', $request->course);
                });
            }
            if ($request->has('section')) {
                $query->whereHas('section', function ($q) use ($request) {
                    $q->where('section_name', $request->section);
                });
            }
            if ($request->has('status')) {
                $query->where('enrollment_status', $request->status);
            }
            if ($request->has('is_active')) {
                $query->where('is_active', $request->is_active);
            }

            // 📄 Paginate
            $students = $query->paginate($perPage, ['*'], 'page', $page);

            $results = [];

            foreach ($students as $student) {
                $admission  = $student->admission;
                $curriculum = $student->curriculum;

                $groupedSubjects = [];
                $totalUnits      = 0;

                if ($curriculum) {
                    $subjects = DB::table('student_subjects as ss')
                        ->join('subjects as s', 'ss.subject_id', '=', 's.id')
                        ->join('curriculum_subject as cs', 'cs.subject_id', '=', 's.id')
                        ->join('school_years as sy', 'ss.school_year_id', '=', 'sy.id')
                        ->where('ss.student_id', $student->id)
                        ->where('cs.curriculum_id', $curriculum->id)
                        ->select(
                            's.id as subject_id',
                            's.subject_name',
                            's.units',
                            's.grade_level_id',
                            'sy.semester'
                        )
                        ->get();

                    foreach ($subjects as $subj) {
                        $key = "Grade {$subj->grade_level_id} - {$subj->semester}";

                        $groupedSubjects[$key][] = [
                            'subject_id'   => $subj->subject_id,
                            'subject_name' => $subj->subject_name,
                            'units'        => $subj->units,
                        ];

                        $totalUnits += $subj->units;
                    }
                }

                // 📝 Final Student Data

                $results[] = [
                    'id'             => $student->id,
                    'student_number' => $student->student_number,
                    'status'         => $student->enrollment_status,
                    'payment_status' => $student->payment_status,
                    'grade_level'    => $admission?->gradeLevel?->grade_level,
                    'course'         => $admission?->course?->course_name,
                    'campus'         => $admission?->campus?->campus_name,

                    'is_active'      => $student->is_active,
                    'applicant' => [
                        'admission_id' => $admission?->id,
                        'first_name'   => $admission?->first_name,
                        'last_name'    => $admission?->last_name,
                        'email'        => $admission?->email,
                        'contact'      => $admission?->contact_number,
                    ],
                    'section' => [
                        'section_id'   => $student->section?->id,
                        'section_name' => $student->section?->section_name,
                    ],
                    'curriculum' => $curriculum ? [
                        'id'          => $curriculum->id,
                        'name'        => $curriculum->curriculum_name,
                        'description' => $curriculum->curriculum_description,
                    ] : null,
                    'subjects_by_semester' => $groupedSubjects,
                    'total_units'          => $totalUnits,
                ];
            }

            return response()->json([
                'isSuccess'  => true,
                'data'       => $results,
                'pagination' => [
                    'total'        => $students->total(),
                    'per_page'     => $students->perPage(),
                    'current_page' => $students->currentPage(),
                    'last_page'    => $students->lastPage(),
                ],
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'isSuccess' => false,
                'message'   => 'Error: ' . $e->getMessage(),
            ], 500);
        }
    }







    //Curriculum Subjects
    //This function retrieves the subjects in the curriculum for the authenticated student.
    //It checks if the student has an admission record and fetches the curriculum based on the course ID.
    //It returns a JSON response with the subjects or an error message if not found.

    public function getCurriculumSubjects(Request $request)
    {
        try {
            // Logged-in student
            $student = auth()->user();

            if (!$student) {
                return response()->json([
                    'isSuccess' => false,
                    'message'   => 'Student not found.',
                ], 404);
            }

            $curriculumId = $student->curriculum_id;

            if (!$curriculumId) {
                return response()->json([
                    'isSuccess' => false,
                    'message'   => 'No curriculum assigned to this student.',
                ], 404);
            }

            // ✅ Get latest enrollment to decide the next school year
            $lastEnrollment = DB::table('enrollments')
                ->where('student_id', $student->id)
                ->orderByDesc('school_year_id')
                ->first();

            $targetSchoolYearId = $lastEnrollment
                ? $lastEnrollment->school_year_id + 1
                : $student->academic_year_id; // fallback if no enrollment yet

            // 🔽 Base query with left join to student_subjects
            $query = DB::table('curriculum_subject as cs')
                ->join('subjects as s', 'cs.subject_id', '=', 's.id')
                ->leftJoin('student_subjects as ss', function ($join) use ($student) {
                    $join->on('s.id', '=', 'ss.subject_id')
                        ->where('ss.student_id', $student->id);
                })
                ->where('cs.curriculum_id', $curriculumId)
                ->where('s.school_year_id', $targetSchoolYearId) // ✅ use next school year
                ->whereNull('ss.final_rating') // ✅ only show subjects with no grade yet
                ->select('s.id', 's.subject_code', 's.subject_name', 's.units');

            // 🔽 Search filter
            if ($request->has('search') && !empty($request->search)) {
                $search = $request->search;
                $query->where(function ($q) use ($search) {
                    $q->where('s.subject_code', 'LIKE', "%{$search}%")
                        ->orWhere('s.subject_name', 'LIKE', "%{$search}%");
                });
            }

            $subjects = $query->get();

            return response()->json([
                'isSuccess'          => true,
                'curriculum_id'      => $curriculumId,
                'eligible_year_id'   => $targetSchoolYearId, // ✅ just so you know what year it used
                'subjects'           => $subjects
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'isSuccess' => false,
                'message'   => 'Error: ' . $e->getMessage(),
            ], 500);
        }
    }





    //DROPDOWN
    public function getGradeLevelsDropdown()
    {
        try {
            $gradeLevels = DB::table('grade_levels')
                ->where('is_archived', 0)
                ->orderBy('grade_level', 'asc')
                ->get(['id', 'grade_level', 'description']);

            return response()->json([
                'isSuccess' => true,
                'gradelevel' => $gradeLevels
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'isSuccess' => false,
                'message' => 'Error fetching grade levels: ' . $e->getMessage(),
            ], 500);
        }
    }



    //HELPERS
    public function getAvailableSubjects(Request $request)
    {
        try {

            $studentId = $request->input('student_id');

            if (!$studentId) {
                return response()->json([
                    'isSuccess' => false,
                    'message'   => 'Student ID is required.',
                ], 400);
            }


            $student = DB::table('students')->where('id', $studentId)->first();

            if (!$student) {
                return response()->json([
                    'isSuccess' => false,
                    'message'   => 'Student not found.',
                ], 404);
            }

            $curriculumId = $student->curriculum_id;

            if (!$curriculumId) {
                return response()->json([
                    'isSuccess' => false,
                    'message'   => 'No curriculum assigned to this student.',
                ], 404);
            }


            $lastEnrollment = DB::table('enrollments')
                ->where('student_id', $student->id)
                ->orderByDesc('school_year_id')
                ->first();

            $targetSchoolYearId = $lastEnrollment
                ? $lastEnrollment->school_year_id + 1
                : $student->academic_year_id;


            $query = DB::table('curriculum_subject as cs')
                ->join('subjects as s', 'cs.subject_id', '=', 's.id')
                ->leftJoin('student_subjects as ss', function ($join) use ($student) {
                    $join->on('s.id', '=', 'ss.subject_id')
                        ->where('ss.student_id', $student->id);
                })
                ->where('cs.curriculum_id', $curriculumId)
                ->where('s.school_year_id', $targetSchoolYearId)
                ->whereNull('ss.final_rating')
                ->select('s.id', 's.subject_code', 's.subject_name', 's.units');


            if ($request->has('search') && !empty($request->search)) {
                $search = $request->search;
                $query->where(function ($q) use ($search) {
                    $q->where('s.subject_code', 'LIKE', "%{$search}%")
                        ->orWhere('s.subject_name', 'LIKE', "%{$search}%");
                });
            }

            $subjects = $query->get();

            return response()->json([
                'isSuccess'          => true,
                'student_id'         => $student->id,
                'curriculum_id'      => $curriculumId,
                'eligible_year_id'   => $targetSchoolYearId,
                'subjects'           => $subjects
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'isSuccess' => false,
                'message'   => 'Error: ' . $e->getMessage(),
            ], 500);
        }
    }
}
