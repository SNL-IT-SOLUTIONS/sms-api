<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AccountsController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\CoursesController;
use App\Http\Controllers\EnrollmentsController;
use App\Http\Controllers\SchoolYearsController;
use App\Http\Controllers\SectionsController;
use App\Http\Controllers\UserTypesController;
use App\Http\Controllers\SchoolCampusController;
use App\Http\Controllers\AdmissionsController;
use App\Http\Controllers\SocialAuthController;
use App\Http\Controllers\SubjectsController;
use App\Http\Controllers\DepartmentsController;
use App\Http\Controllers\ScheduleController;
use App\Http\Controllers\CurriculumController;
use App\Http\Controllers\CampusBuildingsController;
use App\Http\Controllers\BuildingRoomsController;
use App\Http\Controllers\GradeLevelsController;



/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| is assigned the "api" middleware group. Enjoy building your API!
|
*/
// Done Integration

Route::post('verifyaccount', [AccountsController::class, 'verifyAccount']);
Route::get('/login/google', [SocialAuthController::class, 'redirectToGoogle']);
Route::get('/login/google/callback', [SocialAuthController::class, 'handleGoogleCallback']);

Route::get('/auth/github/redirect', [SocialAuthController::class, 'redirectToGithub']);
Route::get('/auth/github/callback', [SocialAuthController::class, 'handleGithubCallback']);
// Admissions


Route::post('createuser', [AccountsController::class, 'createUser']);
Route::post('createadmin', [AccountsController::class, 'createAdminAccount']);
Route::post('updateuser/{id}',[AccountsController::class, 'updateUser']);
Route::get('getusertypes', [UserTypesController::class, 'getUserTypes']);

// Login and Logout 
Route::post('login', [AuthController::class, 'login']);
Route::post('forgotpassword', [AuthController::class, 'forgotPassword']);
Route::middleware('auth:sanctum')->group(function () {
    Route::post('logout', [AuthController::class, 'logout']);
});

//Courses Management
Route::middleware('auth:sanctum')->group(function () {
    Route::get('getcourses', [CoursesController::class, 'getCourses']);
    Route::post('addcourse', [CoursesController::class, 'addCourse']);
    Route::post('deletecourse/{id}', [SchoolCampusController::class, 'deleteCourse']);
    Route::get('/courses/{id}/subjects', [CoursesController::class, 'getCourseSubjects']);

});

// Subjects Management

Route::middleware('auth:sanctum')->group(function () {
    Route::get('getsubjects', [SubjectsController::class, 'getSubjects']);
    Route::post('addsubject', [SubjectsController::class, 'addSubject']);
    Route::post('updatesubject/{id}', [SubjectsController::class, 'updateSubject']);
    Route::post('deletesubject/{id}', [SubjectsController::class, 'deleteSubject']);
    });

//Manage Department

Route::get('getdepartments', [DepartmentsController::class, 'getDepartments']);
Route::post('adddepartment', [DepartmentsController::class, 'addDepartment']);
Route::post('updatedepartment/{id}', [DepartmentsController::class, 'updateDepartment']);
Route::post('deletedepartment/{id}', [DepartmentsController::class, 'deleteDepartment']);




// Create User
// Route::post('createuser', [AccountsController::class, 'createUser']);
// Route::post('createadmin', [AccountsController::class, 'createAdminAccount']);
Route::get('getusers', [AccountsController::class, 'getUsers']);
// Route::post('updateprofile', [AccountsController::class, 'updateProfile'])->middleware('auth:sanctum');

// // Admissions Management
Route::get('getexaminees', [AdmissionsController::class, 'getExamSchedules']);
Route::get('getadmissions', [AdmissionsController::class, 'getAdmissions']);
Route::post('applyadmission', [AdmissionsController::class, 'applyAdmission']);
Route::post('sendcustomemail', [AdmissionsController::class, 'sendCustomEmail'])->middleware('auth:sanctum');
Route::post('sendexamination', [AdmissionsController::class, 'sendExamination'])->middleware('auth:sanctum');
Route::post('deleteadmission/{id}', [AdmissionsController::class, 'deleteAdmission']);
Route::post('/reserve-slot/{id}', [AdmissionsController::class, 'reserveSlot']);
Route::post("examscores", [AdmissionsController::class, 'inputExamScores']);
Route::post('sendexamresult', [AdmissionsController::class, 'sendBulkExamResults']);

Route::get('getexamscoresummary', [AdmissionsController::class, 'getExamScoreSummary']);

Route::middleware('auth:sanctum')->group(function () {
Route::post('sendemail', [AdmissionsController::class, 'sendManualAdmissionEmail'])->middleware('auth:sanctum');
Route::post('acceptadmission/{id}', [AdmissionsController::class, 'acceptapplication'])->middleware('auth:sanctum');
Route::post('approveadmission/{id}', [AdmissionsController::class, 'approveAdmission'])->middleware('auth:sanctum');
Route::post('rejectadmission/{id}', [AdmissionsController::class, 'rejectAdmission'])->middleware('auth:sanctum');
});



// //Enrellments Management
Route::get('getpassedexaminees', [EnrollmentsController::class, 'getPassedStudents']);
Route::get('getenrollments', [EnrollmentsController::class, 'getExamineesResult']);
Route::middleware('auth:sanctum')->group(function () {
    
    Route::post('enrollstudent', [EnrollmentsController::class, 'enrollStudent']);
     Route::post('enrollnow', [EnrollmentsController::class, 'enrollNow']);
     Route::get('getcurriculumsubject', [EnrollmentsController::class, 'getCurriculumSubjects']);

});


// Year Levels Management
Route::middleware('auth:sanctum')->group(function () {
    Route::get('getgradelevel', [GradeLevelsController::class, 'getgradeLevels']);
    Route::post('creategradelevel', [GradeLevelsController::class, 'creategradeLevels']);
    Route::post('updategradelevel/{id}', [GradeLevelsController::class, 'updategradeLevels']);
    Route::post('deletegradelevel/{id}', [GradeLevelsController::class, 'deletegradeLevels']);
    
});



//Assign Schedule
Route::post('/assign-schedule', [ScheduleController::class, 'assignSchedule']);



// // Account Registration and Verification
// Route::post('register', [AccountsController::class, 'registerAccount']);
// Route::post('verifyaccount', [AccountsController::class, 'verifyAccount']);

// // School Campus Management
Route::get('getcampuses', [SchoolCampusController::class, 'getCampuses']);
Route::middleware('auth:sanctum')->group(function (){ 
Route::post('addcampus', [SchoolCampusController::class, 'addCampus']);
Route::post('updatecampus/{id}', [SchoolCampusController::class, 'updateCampus']);
Route::post('deletecampus/{id}', [SchoolCampusController::class, 'deleteCampus']);
 });


// Account Management
Route::middleware('auth:sanctum')->group(function () {
//     Route::get('getaccounts', [AccountsController::class, 'getAccounts']);
//     Route::post('addaccount', [AccountsController::class, 'adminCreateAccount']);
//     Route::get('getprofile', [AccountsController::class, 'getProfile']);
//     Route::post('changeprofile', [AccountsController::class, 'changeProfile']);
    Route::post('changepassword', [AccountsController::class, 'changePassword']);
//     Route::post('deleteaccount', [AccountsController::class, 'deleteAccount']);
//     Route::get('restoreaccount', [AccountsController::class, 'restoreAccount']);
});

// // User Types Management
Route::middleware('auth:sanctum')->group(function (){ 
    Route::post('createusertype', [UserTypesController::class, 'createUserType']);
    Route::post('updateusertype/{id}', [UserTypesController::class, 'updateUserType']);
    Route::post('deleteusertype/{id}', [UserTypesController::class, 'deleteUserType']);
//     Route::post('restoreusertype/{id}', [UserTypesController::class, 'restoreUserType']);
});



//   // Courses Management

Route::middleware('auth:sanctum')->group(function () {
    Route::get('getcourses', [CoursesController::class, 'getCourses']);
    Route::post('updatecourse/{id}', [CoursesController::class, 'updateCourse']);
    Route::post('deletecourse/{id}', [CoursesController::class, 'deleteCourse']);
    Route::get('courses/{id}/subjects', [CoursesController::class, 'getCourseSubjects']);
    Route::get('courses/{id}/curriculums', [CoursesController::class, 'getCourseCurriculums']);

    Route::post('restorecourse/{id}', [CoursesController::class, 'restoreCourse']);
});

//CURRICULUM

Route::get('getcurriculums', [CurriculumController::class, 'getCurriculums']);
Route::get('getcurriculums/{id}', [CurriculumController::class, 'showcurriculums']);
Route::post('createcurriculums', [CurriculumController::class, 'storecurriculum']);
Route::post('updatecurriculums/{id}', [CurriculumController::class, 'updatecurriculum']);

Route::post('deletecurriculums/{id}', [CurriculumController::class, 'delete']);


// // School Years Management
Route::middleware('auth:sanctum')->group(function () {
    Route::post('addschoolyear', [SchoolYearsController::class, 'createSchoolYear']);
    Route::get('getschoolyears', [SchoolYearsController::class, 'getSchoolYears']);
    Route::post('deleteschoolyear', [SchoolYearsController::class, 'deleteSchoolYear']);
    Route::post('updateschoolyear/{id}', [SchoolYearsController::class, 'updateSchoolYear']);

});

// // Sections Management
Route::middleware('auth:sanctum')->group(function () {
    Route::post('addsection', [SectionsController::class, 'addSection']);
    Route::get('getsections', [SectionsController::class, 'getSections']);
    Route::post('updatesection/{id}', [SectionsController::class, 'updateSection']);
    Route::post('deletesection/{id}', [SectionsController::class, 'deleteSection']);
//     Route::post('restoresection/{id}', [SectionsController::class, 'restoreSection']);
});

// Campus Buildings Management
Route::middleware('auth:sanctum')->group(function () {
    Route::get('getbuildings', [CampusBuildingsController::class, 'getBuildings']);
    Route::post('createbuilding', [CampusBuildingsController::class, 'createBuilding']);
    Route::post('updatebuilding/{id}', [CampusBuildingsController::class, 'updateBuilding']);
    Route::post('deletebuilding/{id}', [CampusBuildingsController::class, 'deleteBuilding']);
});

//Rooms Management
Route::middleware('auth:sanctum')->group(function () {
    Route::get('getrooms', [BuildingRoomsController::class, 'getRooms']);
    Route::post('createroom', [BuildingRoomsController::class, 'createRoom']);
    Route::post('updateroom/{id}', [BuildingRoomsController::class, 'updateRoom']);
    Route::post('deleteroom/{id}', [BuildingRoomsController::class, 'deleteRoom']);
});

//Dropdowns
Route::prefix('dropdown')->group(function () {
    Route::get('getstatuses', [AdmissionsController::class, 'getAdmissionStatuses']);
    Route::get('academic-programs', [AdmissionsController::class, 'getAcademicProgramsDropdown']);
    Route::get('academic-years', [AdmissionsController::class, 'getAcademicYearsDropdown']);
    Route::get('schoolyears', [AdmissionsController::class, 'getUniqueSchoolYearsDropdown']);
    Route::get('courses', [SectionsController::class, 'getCoursesDropdown']);
    Route::get('sections', [SectionsController::class, 'getSectionsDropdown']);
    Route::get('subjects', [SubjectsController::class, 'getSubjectsDropdown']);
    Route::get('campuses', [AdmissionsController::class, 'getCampusDropdown']);
    Route::get('rooms/{buildingid}', [AdmissionsController::class, 'getByBuilding']);
    Route::get('buildings/{campusId}', [AdmissionsController::class, 'getBuildingsByCampus']);
    // Route::post('buildingsbycampus/{id}', [AdmissionsController::class, 'getBuildingbyCampus']);



});


