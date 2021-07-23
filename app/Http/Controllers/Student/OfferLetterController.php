<?php

namespace App\Http\Controllers\Student;

use Auth;
use DB;
use File;
use Storage;
use Validator;
use Carbon\Carbon;
use App\Models\Student\Student;
use App\Http\Controllers\Controller;
use App\Models\AgentDetail;
use App\Models\CommissionStatus;
use App\Models\StudentChecklist;
use App\Models\CourseMatrix;
use App\Models\CoursePackage;
use App\Models\Student\OfferLetter\OfferLetter;
use App\Models\CompletionStudentCourse;
use App\Models\OfferLetterStudentDetail;
use App\Models\OfferLetterCourseDetail;
use App\Models\OfferLetterFee;
use App\Models\OfferLetterStatus;
use App\Models\PaymentScheduleTemplate;
use App\Models\PaymentStatus;
use App\Models\StudentCompletion;
use App\Models\StudentCompletionDetail;
use App\Models\Course;
use App\Models\FundedStudentCourse;
use App\Models\FundedStudentCourseDetail;
use App\Models\OfferLetterCourseEnrolmentDetail;
use App\Models\Attendance;
use App\Models\FundedStudentPaymentDetails;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use App\Models\StudentAttachment;
use App\Models\StudentInvoice;
use App\Http\Controllers\Send\EmailSendingController;

use App\Http\Controllers\Student\StudentInvoiceController;
use App\Models\AgentCommissionSettingMain;
use App\Models\AgentCommissionSettingSub;
use App\Models\CourseProspectus;
use App\Models\EmailAutomation;
use App\Models\EnrolmentPack;
use App\Models\TrainingDeliveryLoc;
use App\Models\TrainingOrganisation;
use PDO;

class OfferLetterController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
    }
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        //
        // return view('offer-letter.index');
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create()
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        $this->validate($request, [
            'issue_date' => 'required',
            'courseInfo.selected_package' => 'required|array',
        ], ['courseInfo.selected_package.required' => 'The selected package is required']);
        // declaration of models
        $student = new Student;
        $offerLetter = new OfferLetter;
        $ol_student_detail = new OfferLetterStudentDetail;

        $ol_fee = new OfferLetterFee;

        try {
            DB::beginTransaction();
            $offerLetter->fill([
                'reference_id'      => $request->reference_id,
                'agent_id'          =>  $request->agent,
                'issued_date'       => $request->issue_date != "" ? Carbon::parse($request->issue_date)->format('Y-m-d') : '',
                'is_sent'           => 0,
                'remarks'           => $request->remarks,
                'shore_type'        => $request->courseInfo['shore_type'],
                'package_type'      => $request->courseInfo['package_type'] == "1" ? 'Package' : 'Non-Package',

            ]);

            $offerLetter->student()->associate($request->student_id);
            $offerLetter->user()->associate(Auth::user());
            $offerLetter->save();

            $ol_student_detail->fill([
                'current_address' => $request->current_address,
                'home_address'      => $request->home_address,
                'country'           => $request->country,
                'state_province'    => $request->state_province,
                'postcode'          => $request->postcode,
                'mobile'            => $request->mobile,
                'telephone'         => $request->telephone,
                'email_personal'    => $request->email_personal,
                'country_birth'     => $request->country_of_birth,
                'visa_no'           => $request->visa_num,
                'passport_no'       => $request->passport_num,
                'passport_exp_date' => $request->expiry_date != '' ? Carbon::parse($request->expiry_date)->format('Y-m-d') : ''
            ]);

            $ol_student_detail->offer_letter()->associate($offerLetter);
            $ol_student_detail->save();
            $package_structure = $request->package_structure;
            $ol_fee->fill([
                'course_tuition_fee' => $package_structure['tuition_fee'],
                'application_fee' => $package_structure['application_fee'],
                'materials_fee' => $package_structure['material_fee'],
                'total_course_fee' => $package_structure['total_course_fee'],
                'oshc' => $package_structure['oshc'],
                'total_course_fee_due' => $package_structure['total_course_fee_due'],
                'payment_required' => $package_structure['downpayment'],
                'initial_payment_amount' => $package_structure['initial_payment_amount'],
                'installment_desired_amount' => $package_structure['installment_amount'],
                'installment_interval' => $package_structure['weekly_interval'],
                'installment_start_date' => $package_structure['installment_start_date'] != ''  ? Carbon::parse($package_structure['installment_start_date'])->timezone('Australia/Melbourne')->format('Y-m-d') : null,
            ]);

            $ol_fee->offer_letter()->associate($offerLetter);
            $ol_fee->save();

            $courseInfo = $request->courseInfo;
            $courses = $courseInfo['selected_package'];

            if (empty($courses)) {
                DB::rollback();
                return response()->json(['status' => 'error', 'message' => 'no selected package']);
            }
            foreach ($courses as $key => $course) {
                $ol_course_detail = new OfferLetterCourseDetail;
                $funded_course_detail = new FundedStudentCourse;
                $fcds = new FundedStudentCourseDetail;
                $ol_course_detail->fill([
                    'package_batch' => 0,
                    'course_code'   => $course['code'],
                    'cricos_code'     => $course['criscos'],
                    'trainer_id'    => null,
                    'week_duration' => $course['duration'],
                    'max_week_duration' => $course['max_duration'],
                    'tuition_fees'      => $course['tuition'],
                    'max_tuition_fee'  => $course['max_tuition'],
                    'material_fees'     => $course['material'],
                    'weekly_payment'   => $package_structure['weekly_payment'],
                    'commition_limit'   => 0,
                    'course_start_date' => Carbon::parse($course['course_start'])->format('Y-m-d'),
                    'course_end_date' => Carbon::parse($course['course_end'])->format('Y-m-d'),
                    'status_id' => 1,
                    'order' => $key + 1
                ]);



                $ol_course_detail->offer_letter()->associate($offerLetter);
                if ($courseInfo['package_id'] != '') {
                    $ol_course_detail->package()->associate($courseInfo['package_id']);
                } else {
                    $ol_course_detail->course_matrix()->associate($course['course_matrix_id']);
                }
                $ol_course_detail->save();
                $funded_course_detail->fill([
                    'student_id' => $request->student_id,
                    'course_code' => $course['code'],
                    'eligibility' => 'NE',
                    'location' => $request->state_province,
                    'start_date' => Carbon::parse($course['course_start'])->format('Y-m-d'),
                    'end_date' => Carbon::parse($course['course_end'])->format('Y-m-d'),
                    'course_fee' => $course['tuition'],
                    'course_fee_type' => 'FF',
                    'status_id' => 1,
                    'agent_id' => $request->agent,
                    'user_id' => \Auth::user()->id,
                ]);
                $funded_course_detail->offer_detail()->associate($ol_course_detail);
                $funded_course_detail->save();
                $fcds->funded_student_course()->associate($funded_course_detail);
                $fcds->save();
                $sc = new StudentCompletion;
                $sc->fill([
                    'student_id' => $request->student_id,
                    'course_code' => $course['code'],
                    'user_id' => \Auth::user()->id,
                    'active' => ($key == 0) ? 1 : 0
                ]);
                $sc->save();

                $completion_student_course = new CompletionStudentCourse;
                $completion_student_course->fill(['student_type' => 1]);
                $completion_student_course->completion()->associate($sc);
                $completion_student_course->offer_details()->associate($ol_course_detail);
                $completion_student_course->save();


                $_course = Course::where('code', $course['code'])->first();
                $prospectuses = $_course->courseprospectus->first();
                if ($prospectuses == null) {
                    DB::rollback();
                    return response()->json(['status' => 'error', 'message' => $course['code'] . ' - ' . $_course->name . ' no selected course details . please check']);
                }
                foreach ($prospectuses->unit_selected as $prosectus) {
                    $scd = new StudentCompletionDetail;
                    $scd->fill([
                        'course_unit_code' => $prosectus->code,
                        'start_date' => $ol_course_detail->course_start_date,
                        'end_date' => $ol_course_detail->course_end_date
                    ]);
                    $scd->completion()->associate($sc);
                    $scd->save();
                }
            }

            if ($package_structure['balance_due'] != '0.00') {
                    $this->createPayment($offerLetter);
                
                // $this->updatePayment($course, $package_structure, $package_structure['weekly_payment']);
            }
            DB::commit();

            if($request->agent != ''){
                $this->addUpdateAgentCommission($offerLetter);
                $this->emailAgent($funded_course_detail);
            }
           
            return response()->json(['status' => 'success']);
        } catch (\Throwable $th) {
            //throw $th;
            DB::rollback();
            return response()->json(['status' => 'error', 'message' => $th->getMessage()]);
        }
    }

    /**
     * Display the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function show($id)
    {
        // 
        // dd($id);

        $offerLetter = OfferLetter::with('student_details',
        'course_details.payment_template', 
        'course_details.funded_course.collection.agent',
        'course_details.funded_course.collection.student.party',
        'course_details.funded_course.collection.payment_sched',
        'course_details.funded_course.collection.funded_student_course.course',
        'course_details.funded_course.collection.attachment',
        'course_details.funded_course.payment_sched',
        'course_details.funded_course.payment_details',
        'course_details.payments',
        'course_details.payments.agent' ,
        'course_details.package.detail.course.detail', 
        'course_details.course_matrix.detail', 
        'course_details.enrolment', 
        'course_details.funded_course.detail',
         'fees')->where('student_id', $id)->orderBy('id', 'DESC')->get();
        // $offerLetter->course_details->payments->user->roles = $offerLetter->course_details->payments->user->roles[0]->name;
        // foreach($offerLetter->course_details as $cd){
        //     dump($cd);
        // }
        // return $offerLetter;
        foreach($offerLetter as $ol){
            foreach($ol->course_details as $cd){
                $cd->collection = $cd->funded_course->collection;
            }
        }
        \JavaScript::put([
            'student_id' => $id,
        ]);

        return $offerLetter;
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function edit($id)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, OfferLetter $offer_letter)
    {
        // dump('2020-04-07T16:00:00.000Z');
        // dump(Carbon::parse(strtotime('2020-04-07T16:00:00.000Z'))->format('Y-m-d'));

        // dump(Carbon::createFromFormat('Y-m-d', substr('2020-04-07T16:00:00.000Z', 0, 10))->format('Y-m-d'));
        try {
            DB::beginTransaction();
            $offer_letter->update([
                'reference_id'      => $request->reference_id,
                'agent_id'          => $request->agent,
                'issued_date'       => $request->issued_date != "" ? Carbon::parse($request->issued_date)->timezone('Australia/Melbourne')->format('Y-m-d') : '',
                'is_sent'           => 0,
                'remarks'           => $request->remarks,
                'shore_type'        => $request->student_type == 1 ? 'International' : 'Domestic',
                'package_type'      => $request->courseInfo['package_type'] == NULL ? $offer_letter->package_type : (($request->courseInfo['package_type'] == '1') ? 'Package' : 'Non-Package'),
            ]);

            $offer_letter->student_details->update([
                'current_address'   => $request->current_address,
                'home_address'      => $request->home_address,
                'country'           => $request->country,
                'state_province'    => $request->state_province,
                'postcode'          => $request->postcode,
                'mobile'            => $request->mobile,
                'telephone'         => $request->telephone,
                'email_personal'    => $request->email_personal,
                'country_birth'     => $request->country_of_birth,
                'visa_no'           => $request->visa_num,
                'passport_no'       => $request->passport_num,
                'passport_exp_date' => $request->expiry_date != '' && $request->expiry_date != 'Invalid date' ? Carbon::parse($request->expiry_date)->timezone('Australia/Melbourne')->format('Y-m-d') : ''
            ]);

            $package_structure = $request->package_structure;
            $offer_letter->fees->update([
                'course_tuition_fee' => $package_structure['tuition_fee'],
                'application_fee' => $package_structure['application_fee'],
                'materials_fee' => $package_structure['material_fee'],
                'total_course_fee' => $package_structure['total_course_fee'],
                'oshc' => $package_structure['oshc'],
                'total_course_fee_due' => $package_structure['total_course_fee_due'],
                'payment_required' => $package_structure['downpayment'],
                'payment_due' => $package_structure['payment_due'] != ''  ? Carbon::parse($package_structure['payment_due'])->timezone('Australia/Melbourne')->format('Y-m-d') : null,
                'initial_payment_amount' => $package_structure['initial_payment_amount'],
                'discounted_amount' => $package_structure['discounted_amount'],
                'balance_due' => $package_structure['balance_due'],
                'installment_desired_amount' => $package_structure['installment_amount'],
                'installment_interval' => $package_structure['weekly_interval'],
                'installment_start_date' => $package_structure['installment_start_date'] != ''  ? Carbon::parse($package_structure['installment_start_date'])->timezone('Australia/Melbourne')->format('Y-m-d') : null,
            ]);
            // dd($offer_letter->fees->wasChanged('initial_payment_amount'));

            $courses = $offer_letter->course_details;
            $courseInfo = $request->courseInfo;
            $_courses = $courseInfo['selected_package'];
            foreach ($courses as $course) {
                $dirty = [];
                foreach ($_courses as $_course) {
                    $start = '';
                    $end  = '';
                    if ($course->course_code == $_course['code'] && $course->cricos_code == $_course['criscos']) {
                        // $course->update([
                        //     'week_duration' => $_course['duration'],
                        //     'tuition_fees'      => $_course['tuition'],
                        //     'material_fees'     => $_course['material'],
                        //     'course_start_date' => Carbon::parse($_course['course_start'])->format('Y-m-d'),
                        //     'course_end_date' => Carbon::parse($_course['course_end'])->format('Y-m-d')
                        // ]);
                        $start = $_course['course_start'];
                        $end = $_course['course_end'];
                        $course->week_duration = $_course['duration'];
                        $course->tuition_fees = $_course['tuition'];
                        $course->material_fees = $_course['material'];
                        // $course->material_fees = $_course['material'];
                        $course->course_start_date = Carbon::parse($start)->timezone('Australia/Melbourne')->format('Y-m-d');
                        $course->course_end_date = Carbon::parse($end)->timezone('Australia/Melbourne')->format('Y-m-d');
                        $course->weekly_payment = isset($package_structure['weekly_payment']) ? $package_structure['weekly_payment'] : 0;
                        $funded_course = $course->funded_course;
                        if ($funded_course != null) {
                            $funded_course->course_fee = $package_structure['total_course_fee'] - $package_structure['discounted_amount'];
                            $funded_course->start_date = Carbon::parse($start)->timezone('Australia/Melbourne')->format('Y-m-d');
                            $funded_course->end_date = Carbon::parse($end)->timezone('Australia/Melbourne')->format('Y-m-d');
                            $funded_course->agent_id = $request->agent;
                            $funded_course->save();
                        }



                        $dirty = $course->getDirty();

                        $course->save();
                    }
                }
                if ($offer_letter->fees->wasChanged('initial_payment_amount') || $offer_letter->fees->wasChanged('discounted_amount') || $course->wasChanged('weekly_payment') ||$offer_letter->fees->wasChanged('payment_due') || $offer_letter->fees->wasChanged('installment_start_date') || $offer_letter->fees->wasChanged('installment_interval') || $offer_letter->fees->wasChanged('installment_desired_amount')) {
                    if ($course->payment_template()->has('payment_detail')->count() == 0) {
                        if ($course->order == 1) {
                                                        // $course->payment_template()->forceDelete();
                            $this->updatePayment($course, $package_structure, $package_structure['weekly_payment']);
                        }
                    }
                }
                // if (count($dirty) > 0) {
                //     if (isset($dirty['weekly_payment'])) {
                //         $this->updatePayment($course, $package_structure, $dirty['weekly_payment']);
                //     }

                //     if (!empty($course->payment_template)) {
                //         if ($course->payment_template()->has('payment_detail')->count() == 0) {
                //             if ($course->order == 1) {
                //                 // dd('hi');
                //                 // dd($dirty);
                //                 // $course->payment_template()->forceDelete();
                //                 // $this->updatePayment($course, $package_structure);
                //             }
                //         }
                //     }
                // }
            }
            $offer_letter->checklist = json_encode($request->checklist);
            $offer_letter->save();

            if($offer_letter->wasChanged('agent_id')){
                $this->addUpdateAgentCommission($offer_letter);
                $this->emailAgent($offer_letter);
            }


            if (isset($offer_letter->student->id) && $offer_letter->student->student_type_id == 2) {
                $stud_att = new StudentAttachmentController;
                $stud_att->generate_coe($offer_letter->id);
            }


            DB::commit();
            return response()->json(['status' => 'success']);
        } catch (\Throwable $th) {
            DB::rollback();
            throw $th;
        }
    }

    public function addUpdateAgentCommission($offerletter){
        // dd($offerletter);
        try {
            //code...
            $course_details = $offerletter->course_details;
            // dd($course_details);
            foreach($course_details as $detail){
                // DB::rollback();

                if($detail->funded_course != null){
                    // dd($detail);
                    $funded = $detail->funded_course;
                    $acss = new AgentCommissionSettingSub;
                    $agent = AgentCommissionSettingMain::where("agent_id",$offerletter->agent_id)->where('course_id',$funded->course->id)->first();
                    $comm_limit = 0;
                    if($agent->gst_type == 'not_registered'){
                        $comm_limit = $this->commission_not_registered($agent,$offerletter->fees->course_tuition_fee);
                    }else{
                        $comm_limit = $this->commission_registered($agent, $offerletter->fees->course_tuition_fee);
                    }
                    // dd($agent);
                    // dd($comm_limit);
                    $acss->fill([
                        'student_id'                 => $offerletter->student_id,
                        'commission_limit'           => $comm_limit,
                        'gst_type'                   => $agent->gst_type,
                        'gst_status'                 => $agent->gst_status,
                        'registered_date'            => $agent->registered_date,
                        'course_id'                  => $funded->course->id,
                        'student_course_id'          => $funded->id,
                        'commision_value'            => $agent->commision_value,
                        'commission_type'            => $agent->commission_type,
                        'cut_off_period'             => 1,
                    ]);
                    $acss->main()->associate($agent);
                    $acss->save();

                    foreach($funded->payment_sched as $key => $plan){
                        if($key != 0){
                        $funded = $detail->funded_course;
                            if($agent->gst_type == 'not_registered'){
                                $commission = $this->commission_not_registered($acss,$plan->payable_amount);
                            }else{
                                $commission = $this->commission_registered($acss, $plan->payable_amount);
                            }
                                $plan->commission = $commission;
                                $plan->update();
                        }
                        
                    }
                 

                }


                

            }

        } catch (\Throwable $th) {
            DB::rollback();
            throw $th;
        }
    }

    public function commission_not_registered($setting,$tuition){
        if ($setting->gst_status == 0) {
            return round($tuition * ($setting->commision_value / 100), 2);
        }else{
            return round($tuition * ($setting->commision_value / 100) - ($tuition * ($setting->commision_value / 100) * .10), 2);
        }
    }
    public function commission_registered($setting,$tuition)
    {
        if ($setting->gst_status == 0) {
            return round($tuition * ($setting->commision_value / 100), 2);
        } else {
            return  round($tuition * ($setting->commision_value / 100) + ($tuition * ($setting->commision_value / 100) * .10), 2);
        }
    }
    

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id     * @return \Illuminate\Http\Response
     */
    public function destroy(OfferLetter $offer_letter)
    {
        //
        try {
            //code...
            DB::beginTransaction();
            // $offer_letter->course_details->delete();
            // check if payment already made
            $checker =  false;

            foreach ($offer_letter->course_details as $course) {
                $scc = CompletionStudentCourse::where('student_course_id', $course->id)->where('student_type', 1)->first();
                $scc->delete();
                $attendance = Attendance::where('student_id', $offer_letter->student_id)->where('course_code', $course->course_code)->get();
                foreach ($attendance as $ad) {
                    $ad->delete();
                }
                if ($course->payment_template()->has('payment_detail')->count() == 0) {
                    $course->payment_template()->forceDelete();
                    $checker = true;
                } else {
                    $checker = false;
                    break;
                }
            }
            if ($checker) {
                $offer_letter->student_details->delete();
                $offer_letter->fees->delete();
                $offer_letter->delete();
                DB::commit();
                return response()->json(['status' => 'success']);
            } else {
                return response()->json(['status' => 'error', 'message' => 'Payment has been made']);
            }
        } catch (\Throwable $th) {
            DB::rollback();
            return response()->json(['status' => 'error', 'message' => ' ERROR - ' . $th->getMessage()]);
            // throw $th;
        }
    }

    public function getPackage($package_type, $shore_type)
    {
        if ($package_type == 1) {
            $packages = CoursePackage::with('detail.course.detail')->where('shore_type', $shore_type)->get();
        } else {
            $packages = CourseMatrix::with('detail')->get();
        }
        // dd('test');
        return response()->json($packages);
    }

    public function generate_string($input = '0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZ', $strength = 16)
    {
        $input_length = strlen($input);
        $random_string = '';

        for ($i = 0; $i < $strength; $i++) {
            $random_character = $input[mt_rand(0, $input_length - 1)];
            $random_string .= $random_character;
        }

        $validate = PaymentScheduleTemplate::where('invoice_no', $random_string)->first();

        if ($validate) {
            $permitted_chars = '0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZ';
            $this->generate_string($permitted_chars, 12);
        } else {
            return $random_string;
        }
    }

    public function updatePayment($course, $package_structure = null, $weekly_payment = 0)
    {
        set_time_limit(500); //
        try {
            $fees = $course->payment_template;
            DB::beginTransaction();
            $initial = $package_structure['initial_payment_amount'] != '0' ? $package_structure['initial_payment_amount'] : $package_structure['downpayment'];
            $nonTuition  = $course->offer_letter->fees->materials_fee + $course->offer_letter->fees->application_fee;
            $tuition = $course->tuition_fees;
            $balance = 0;
            $discount = 0;
            $duration = 0;
            $startdate = $package_structure['installment_start_date'] == '' ? Carbon::parse($course->course_start_date)->timezone('Australia/Melbourne') : Carbon::parse($package_structure['installment_start_date'])->timezone('Australia/Melbourne');
            if ($initial >= 500) {
                if ($course->offer_letter->fees->discounted_amount == null) {
                    $tfees = $tuition +  $nonTuition - $discount;
                    $balance = $tfees - $initial;
                } else {
                    $discount = $course->offer_letter->fees->discounted_amount;
                    $tfees = $tuition +  $nonTuition - $discount;
                    $balance = $tfees - $initial;
                }
                $balance_ = $balance;
                $totalTuition = $tuition / $course->week_duration;
                $week = round($balance / $totalTuition);


                $bduration = $course->week_duration;
                $duration = ($bduration - $week);
                // $startdate = $startdate->addWeek($duration);
            } else {
                // dd($course->offer_letter->fees->discounted_amount);
                if ($course->offer_letter->fees->discounted_amount == '0.00') {
                    $tfees = $tuition +  $nonTuition - $discount;
                    $balance = $tfees - $initial;
                    
                } else {
                    $discount = $course->offer_letter->fees->discounted_amount;
                    $tfees = $tuition +  $nonTuition - $discount;
                    $balance = $tfees - $initial;
                }
                $balance_ = $balance;
                $totalTuition = $tuition / $course->week_duration;
                $week = round($balance / $totalTuition);
            }
            if ($weekly_payment != 0) {
              
                if (!$fees->isEmpty()) {

                    

                    $weekp = $package_structure['installment_amount'];
                    foreach ($fees as $key => $fee) {
                        if ($key === 0) {
                            $fee->update([
                                'due_date' => $package_structure['payment_due'] == '' ? Carbon::parse($course->course_start_date)->subDays(3)->format('Y-m-d') : Carbon::parse($package_structure['payment_due'])->timezone('Australia/Melbourne')->format('Y-m-d'),
                                'payable_amount' => $initial
                            ]);
                            $invoice_num = $fee->invoice_no;
                            $student_invoice = new StudentInvoiceController;
                            $student_invoice->createInvoice($fee, $course->offer_letter->student_id, $invoice_num, $key);
                            $student_invoice->generate_invoice($course->offer_letter->student_id, $fee->id);
                        } else {
                            $fee->delete();
                        }
                    }
                    $limit = $weekly_payment;
                    $weeklybalance = $balance;
                    // dump($weeklybalance);
                    // dd($limit);
                    $hurot = false;
                    for ($i = 0; $i < $limit; $i++) {

                        if ($weeklybalance < $weekp) {
                            $hurot = true;
                            $weekp = $weeklybalance;
                        }

                        if($i == $limit - 1){
                            $weekp = $weeklybalance;
                        }
                        $acss = $course->funded_course->commission;
                        if($acss->gst_type == 'not_registered'){
                            $comm_limit = $this->commission_not_registered($acss,$weekp);
                        }else{
                            $comm_limit = $this->commission_registered($acss, $weekp);
                        }
                        $payment = new PaymentScheduleTemplate;
                        $permitted_chars = '0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZ';
                        $invoice_no = $this->generate_string($permitted_chars, 12);
                        $payment->fill([

                            'invoice_no' => $invoice_no,
                            'due_date' => $i == 0 ? $startdate->format('Y-m-d') : $startdate->addWeek($package_structure['weekly_interval'])->format('Y-m-d'),
                            'payable_amount' =>  $weekp,
                            'commission'    => $comm_limit
                        ]);
                        $payment->offerLetter()->associate($course->offer_letter_id);
                        $payment->course_detail()->associate($course);
                        if ($course->funded_course != null) {
                            $payment->funded_course_detail()->associate($course->funded_course);
                        }
                        $payment->user()->associate(\Auth::user());
                        if ($weeklybalance != 0) {
                            $student_invoice->createInvoice($payment, $course->offer_letter->student_id, $invoice_num, $key);
                            $student_invoice->generate_invoice($course->offer_letter->student_id, $payment->id);
                            $payment->save();
                        }
                        $weeklybalance = $weeklybalance - $weekp;
                    }
                    // dd('hi');
                } else {
                  
                    // dd('wai unod');
                    $limit = $weekly_payment;
                    $weekp = $package_structure['installment_amount'];
                    $weeklybalance = $balance;
                    // dd($weekp);
                    // dd($weeklybalance);
                    $hurot = false;
                    $payment = new PaymentScheduleTemplate;
                    $permitted_chars = '0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZ';
                    $invoice_no = $this->generate_string($permitted_chars, 12);
                    $payment->fill([
                        'invoice_no' => $invoice_no,
                        'due_date' =>  $package_structure['payment_due'] == '' ? Carbon::parse($course->course_start_date)->subDays(3)->format('Y-m-d') : Carbon::parse($package_structure['payment_due'])->timezone('Australia/Melbourne')->format('Y-m-d'),
                        'payable_amount' =>  $initial
                    ]);
                    $payment->offerLetter()->associate($course->offer_letter_id);
                    $payment->course_detail()->associate($course);
                    if ($course->funded_course != null) {
                        $payment->funded_course_detail()->associate($course->funded_course);
                    }
                    $payment->user()->associate(\Auth::user());
                    $student_invoice = new StudentInvoiceController;
                    $student_invoice->createInvoice($payment, $course->offer_letter->student_id, $invoice_no, 0);
                    $student_invoice->generate_invoice($course->offer_letter->student_id, $payment->id);
                    $payment->save();
                    // $weeklybalance = $weeklybalance - $weekp;
                    for ($i = 0; $i < $limit; $i++) {


                        if ($weeklybalance < $weekp) {
                            $hurot = true;
                            $weekp = $weeklybalance;
                            $weeklybalance = 0;

                        }

                        if($i == $limit - 1){
                            $weekp = $weeklybalance;
                        }
                        $payment1 = new PaymentScheduleTemplate;
                        $permitted_chars = '0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZ';

                        $acss = $course->funded_course->commission;
                        if($acss->gst_type == 'not_registered'){
                            $comm_limit = $this->commission_not_registered($acss,$weekp);
                        }else{
                            $comm_limit = $this->commission_registered($acss, $weekp);
                        }
                        $invoice_no = $this->generate_string($permitted_chars, 12);
                        $payment1->fill([

                            'invoice_no' => $invoice_no,
                            'due_date' =>  $i == 0 ?  $startdate->addDays(3)->format('Y-m-d')  : $startdate->addWeek($package_structure['weekly_interval'])->format('Y-m-d'),
                            'payable_amount' =>  $weekp,
                            'commission'     =>$weekp,
                        ]);
                        $payment1->offerLetter()->associate($course->offer_letter_id);
                        $payment1->course_detail()->associate($course);
                        if ($course->funded_course != null) {
                            $payment1->funded_course_detail()->associate($course->funded_course);
                        }
                        $payment1->user()->associate(\Auth::user());
                        if ($weeklybalance != 0) {
                            // dump($weeklybalance);
                            $student_invoice->createInvoice($payment1, $course->offer_letter->student_id, $invoice_no, $i);
                            $student_invoice->generate_invoice($course->offer_letter->student_id, $payment1->id);
                            $payment1->save();
                        }
                        $weeklybalance = $weeklybalance - $weekp;
                        // dump($weeklybalance);
                    }
                    // $this->createPayment($course->offer_letter);
                    // exit();
                }
            } else {
              
                if (!$fees->isEmpty()) {
                    foreach ($fees as $key => $fee) {
                        $fee->update([
                            'due_date' => $key == 0 ? Carbon::parse($course->course_start_date)->subDays(3)->format('Y-m-d') : $startdate->format('Y-m-d'),
                            'payable_amount' => $key == 0 ? $initial : $balance_
                        ]);
                        if ($key > 1) {
                            $fee->delete();
                        }
                        $invoice_num = $fee->invoice_no;
                        $student_invoice = new StudentInvoiceController;
                        $student_invoice->createInvoice($fee, $course->offer_letter->student_id, $invoice_num, $key);
                        $student_invoice->generate_invoice($course->offer_letter->student_id, $fee->id);
                    }
                } else {
                    $this->createPayment($course->offer_letter);
                }
            }
            // $payment = new PaymentScheduleTemplate;
            // $payment->fill([
            //     'due_date' => Carbon::parse($course->course_start_date)->subDays(3)->format('Y-m-d'),
            //     'payable_amount' => $initial
            // ]);
            // $payment->offerLetter()->associate($course->offer_letter_id);
            // $payment->course_detail()->associate($course);
            // $payment->user()->associate(\Auth::user());
            // $payment->save();

            // $payment = new PaymentScheduleTemplate;
            // $payment->fill([
            //     'due_date' => $startdate->format('Y-m-d'),
            //     'payable_amount' => $balance_
            // ]);

            // $payment->offerLetter()->associate($course->offer_letter_id);
            // $payment->course_detail()->associate($course);
            // $payment->user()->associate(\Auth::user());
            // $payment->save();



            $exist = FundedStudentPaymentDetails::where('student_id', $course->offer_letter->student_id)->where('note', 'Initial Payment')->where('offer_letter_course_detail_id', $course->id)->first();

            if ($exist != null) {
                $exist->delete();
            }

            if ($package_structure['initial_payment_amount'] != '0') {
                $FundedStudentPaymentDetails = new FundedStudentPaymentDetails;
                $FundedStudentPaymentDetails->fill([
                    'student_id' => $course->offer_letter->student_id,
                    'note' => 'Initial Payment',
                    'amount' => $initial,
                    'offer_letter_course_detail_id'     => $course->id,
                    'student_course_id'     => $course->funded_course != null ? $course->funded_course->id : null,
                    'payment_date' => Carbon::now()->format('Y-m-d'),
                    'user_id' => \Auth::user()->id,
                ]);
                $FundedStudentPaymentDetails->save();
            }
            $org = TrainingOrganisation::first();
            $offerData = OfferLetter::with('agent.detail', 'student.party.person', 'course_details.course', 'course_details.course_matrix', 'course_details.package.dlvr_location', 'course_details.payment_template', 'student_details', 'fees')->where('id', $course->offer_letter->id)->first()->toArray();
            $offerData['location'] = $offerData['course_details'][0]['package'] ?  $offerData['course_details'][0]['package']['dlvr_location']['train_org_dlvr_loc_name']  : $offerData['course_details'][0]['course_matrix']['location'];
            $cp = CourseProspectus::where('course_code', $offerData['course_details']['0']['course_code'])->where('location', 'LIKE', '%' . $offerData['location'] . '%')->first();
            
            // $cp = CourseProspectus::where('course_code', $offerData['course_details']['0']['course_code'])->whereIn('location', [$offerData['location']])->first();
            $offerData['dlvr_loc']  = TrainingDeliveryLoc::with('state')->where('train_org_dlvr_loc_id', $cp->train_org_dlvr_loc_id)->first()->toArray();
            // dd($offerData);
            foreach ($offerData['course_details'][0]['payment_template'] as $template) {
                $breakdown[] = [
                    'due_date' => Carbon::parse($template['due_date'])->format('d/m/Y'),
                    'payable' => $template['payable_amount'],
                    'receipt' => ''
                ];
                // dump($template);
            }
            if (count($breakdown) > 15) {
                $breakdowns = array_chunk($breakdown, 17);
                $page = count($breakdowns);
            } else {
                // $breakdowns = $offerData['payment_sched'][0];
                $breakdowns[0] = $breakdown;
                $page = 1;
            }
            $signed = true;
            // dd($offerData);
            $path = storage_path() . '/app/public/student/new/attachments/' . $course->offer_letter->student_id;

            if (!File::isDirectory($path)) {
                File::makeDirectory($path, 0777, true, true);
            }

            // dd($pdf);
            $hashFileName = 'offer-letter-and-enrolment-acceptance-agreement';
            if (env('APP_NAME') == 'Phoenix') {
                if ($offerData['student']['student_type_id'] == 2) {
                    \PDF::loadView('enrolment.pca.offer-letter-enrolment-acceptance-agreement-domestic-pdf', compact('offerData', 'signed', 'org'))->setPaper('A4')->save($path . '/' . $hashFileName . '.pdf');
                }
                 \PDF::loadView('enrolment.pca.offer-letter-enrolment-acceptance-agreement-pdf', compact('offerData', 'signed', 'org'))->setPaper('A4')->save($path . '/' . $hashFileName . '.pdf');
            } else if (env('APP_NAME') == 'CEA') {
            \PDF::loadView('enrolment.cea.offer-letter-enrolment-acceptance-agreement-pdf', compact('offerData', 'signed', 'org'))->setPaper('A4')->save($path . '/' . $hashFileName . '.pdf');


            $pdf = new \PDFMerger;

            $pdf->addPDF($path . '/' . $hashFileName . '.pdf', '1');
            $pdf->addPDF(storage_path() . '/app/public/offer_letter/offerLetterStaticPages.pdf', '1-2');
            $pdf->addPDF($path . '/' . $hashFileName . '.pdf', '2');
            $pdf->addPDF(storage_path() . '/app/public/offer_letter/offerLetterStaticPages.pdf', '3-24');
            $pdf->addPDF($path . '/' . $hashFileName . '.pdf', '3-4');

            $pdf->merge('file', $path . '/' . $hashFileName . '.pdf');
                //  \PDF::loadView('enrolment.cea.offer-letter-enrolment-acceptance-agreement-pdf', compact('offerData', 'signed', 'org'))->setPaper('A4')->save($path . '/' . $hashFileName . '.pdf');
            }

            // if ($course->offer_letter->student->student_type_id == 2) {
            //     \PDF::loadView('enrolment.pca.offer-letter-enrolment-acceptance-agreement-domestic-pdf', compact('offerData', 'signed'))->setPaper('A4')->save($path . '/' . $hashFileName . '.pdf');
            // } else {
            //     \PDF::loadView('enrolment.pca.offer-letter-enrolment-acceptance-agreement-pdf', compact('offerData', 'signed'))->setPaper('A4')->save($path . '/' . $hashFileName . '.pdf');
            // }


            
            $existattachment = StudentAttachment::where('student_id', $course->offer_letter->student_id)->where('_input', 'offer_letter')->first();
            if ($existattachment == null) {
                $pdf = Storage::size('/public/student/new/attachments/' . $course->offer_letter->student_id . '/' . $hashFileName . '.pdf');
                $studentAttachment = new StudentAttachment([
                    'name'      => 'offer-letter-and-enrolment-acceptance-agreement.pdf',
                    'hash_name' => 'offer-letter-and-enrolment-acceptance-agreement',
                    'size'      => $pdf,
                    'mime_type' => 'application/pdf',
                    'ext'       => 'pdf',
                    '_input'       => 'offer_letter',
                ]);
                // associated user
                $studentAttachment->user()->associate(\Auth::user());
                $studentAttachment->student()->associate($course->offer_letter->student_id);
                $studentAttachment->save();
                $studentAttachment->path_id = $course->offer_letter->student_id;
                $studentAttachment->update();
            }


            // dd($totalTuition);
            // for ($i = 0; $i < $duration; $i++) {

            //     $checknegative = $balance_ - $totalTuition;
            //     if ($checknegative > 0) {

            //         $payable = $totalTuition;
            //     } else {
            //         $payable = $balance_;
            //     }
            //     $balance_ = $balance_ - $totalTuition;
            //     // dump($payable);
            //     $payment = new PaymentScheduleTemplate;
            //     $payment->fill([
            //         'due_date' => ($i == 0) ? $startdate->format('Y-m-d') : $startdate->addWeek()->format('Y-m-d'),
            //         'payable_amount' => $payable
            //     ]);
            //     $payment->offerLetter()->associate($course->offer_letter_id);
            //     $payment->course_detail()->associate($course);
            //     $payment->user()->associate(\Auth::user());
            //     $payment->save();
            // }
            // exit();

            // for ($i = 0; $i < $duration; $i++) {

            //     $payment = new PaymentScheduleTemplate;
            //     if ($package_structure != null) {
            //         $payment->fill([
            //             'due_date' => ($i == 0) ? $course_start_date->format('Y-m-d') : $course_start_date->addMonth($i)->format('Y-m-d'),
            //             'payable_amount' => (float) $package_structure['balance_due'] / $monthly
            //         ]);
            //     } else {
            //         $payment->fill([
            //             'due_date' => ($i == 0) ? $course_start_date->format('Y-m-d') : $course_start_date->addMonth($i)->format('Y-m-d'),
            //             'payable_amount' => (float) $course->tuition_fees / $monthly
            //         ]);
            //     }
            //     $payment->offerLetter()->associate($course->offer_letter_id);
            //     $payment->course_detail()->associate($course);
            //     $payment->user()->associate(Auth::user());
            //     $payment->save();
            // }
            DB::commit();
        } catch (\Throwable $th) {
            DB::rollback();
            throw $th;
        }
    }
    public function createPayment($offerLetter)
    {
        // dd('offerlettercontroller');

        // dd($agent);
        $course_fees = $offerLetter->fees;
        $course_detail  = $offerLetter->course_details;
     
        try {
            DB::beginTransaction();
            foreach ($course_detail as $key => $course) {

                if ($key  == 0) {
                    if($course_fees->initial_payment_amount == '0.00'){
                        $balance = $course_fees->total_course_fee_due - $course_fees->discounted_amount;
                    }else{
                        $menus = $course_fees->discounted_amount + $course_fees->initial_payment_amount;
                        $balance = $course_fees->total_course_fee_due - $menus;
                    }
                    $initial_Due = \Carbon\Carbon::parse($course->course_start_date)->subDays(3)->format('Y-m-d');
                    $balance_ = $course_fees->installment_desired_amount;
                    $startdate = Carbon::parse($course_fees->installment_start_date);
                    for ($i = 0; $i <= $course->weekly_payment; $i++) {
                        $permitted_chars = '0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZ';
                        $invoice_no = $this->generate_string($permitted_chars, 12);
                        $payment = new PaymentScheduleTemplate;
                        $payable = $course_fees->initial_payment_amount == 0  ? $course_fees->payment_required : $course_fees->initial_payment_amount;
                        
                        $payment->fill([
                            'invoice_no' => $invoice_no,
                            'due_date' => ($i == 0) ?  $initial_Due : $startdate->format('Y-m-d'),
                            'payable_amount' => ($i == 0) ? $payable : $balance_
                        ]);
                        $payment->offerLetter()->associate($offerLetter);
                        $payment->course_detail()->associate($course);
                        if ($course->funded_course != null) {
                            $payment->funded_course_detail()->associate($course->funded_course);
                        }
                        $payment->user()->associate(\Auth::user());
                        $payment->save();
                        if($i> 0 ){
                            $startdate->addWeek($course_fees->installment_interval);
                        }
                    }

                    
                    // $tuitDur = round($course_fees->payment_required / $course->week_duration);

                    // $totalTuition = $course_fees->course_tuition_fee / $course->week_duration;
                    // $weekCount = $course_fees->initial_payment_amount != 0 ? round($course_fees->initial_payment_amount / $tuitDur) : round($course_fees->payment_required / $tuitDur);
                    // $initial_Due = \Carbon\Carbon::parse($course->course_start_date)->subDays(3)->format('Y-m-d');
                    // $startdate = Carbon::parse($course->course_start_date)->addWeek($tuitDur);
                    // $nonTuition = $course_fees->application_fee + $course_fees->materials_fee;
                    // // $week = round($balance / $totalTuition);
                    // // dd($totalTuition);
                    // // $course_fees->payment_required : $course_fees->initial_payment_amount
                    // // $balance = $course_fees->initial_payment_amount == 0 ?  $course_fees->payment_required  - $nonTuition : $course_fees->initial_payment_amount - $nonTuition;
                    // $balance = $course_fees->initial_payment_amount == 0 ?  $course_fees->payment_required : $course_fees->initial_payment_amount;
                    // $bduration = $course->wk_duration;
                    // $duration = $bduration - $tuitDur;
                    // $balance_ = $course_fees->course_tuition_fee - $course_fees->discounted_amount - $balance;
                    // // dd($balance);
                    // for ($i = 0; $i < 2; $i++) {
                    //     $permitted_chars = '0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZ';
                    //     $invoice_no = $this->generate_string($permitted_chars, 12);
                    //     $payment = new PaymentScheduleTemplate;
                    //     $payable = $course_fees->initial_payment_amount == 0  ? $course_fees->payment_required : $course_fees->initial_payment_amount;
                    //     $payment->fill([
                    //         'invoice_no' => $invoice_no,
                    //         'due_date' => ($i == 0) ?  $initial_Due : $startdate->format('Y-m-d'),
                    //         'payable_amount' => ($i == 0) ? $payable : $balance_
                    //     ]);
                    //     $payment->offerLetter()->associate($offerLetter);
                    //     $payment->course_detail()->associate($course);
                    //     if ($course->funded_course != null) {
                    //         $payment->funded_course_detail()->associate($course->funded_course);
                    //     }
                    //     $payment->user()->associate(\Auth::user());
                    //     $payment->save();
                    // }

                    // for ($i = 0; $i < $duration; $i++) {
                    //     $checknegative = $balance_ - $totalTuition;
                    //     if ($checknegative > 0) {

                    //         $payable = $totalTuition;
                    //     } else {
                    //         $payable = $balance_;
                    //     }
                    //     $balance_ = $balance_ - $totalTuition;
                    //     $payment = new PaymentScheduleTemplate;
                    //     $payment->fill([
                    //         'due_date' => ($i == 0) ? $startdate->format('Y-m-d') : $startdate->addWeek()->format('Y-m-d'),
                    //         'payable_amount' => $payable
                    //     ]);
                    //     $payment->offerLetter()->associate($offerLetter);
                    //     $payment->course_detail()->associate($course);
                    //     $payment->user()->associate(\Auth::user());
                    //     $payment->save();
                    // }
                } else {
                    // $tuitDur = round($course->week_duration);

                    // $totalTuition = $course_fees->course_tuition_fee / $course->week_duration;
                    // $weekCount = $course_fees->payment_required != 0 ? round($course_fees->course_tuition_fee / $tuitDur) : 0;
                    // $startDuration = \Carbon\Carbon::parse($course->course_start_date);
                    // $cstart_date = \Carbon\Carbon::createFromFormat('Y-m-d', $course->course_end_date);
                    // $startdate = Carbon::parse($course->course_start_date)->addWeek($tuitDur);
                    // $nonTuition = $course_fees->application_fee + $course_fees->materials_fee;
                    // // $week = round($balance / $totalTuition);
                    // // dd($totalTuition);
                    // $balance = $course_fees->payment_required - $nonTuition;
                    // $bduration = $course->wk_duration;
                    // $duration = $bduration - $tuitDur;
                    // $balance_ = $course_fees->course_tuition_fee - $balance;

                    // for ($i = 0; $i < $duration; $i++) {
                    //     $checknegative = $balance_ - $totalTuition;
                    //     if ($checknegative > 0) {

                    //         $payable = $totalTuition;
                    //     } else {
                    //         $payable = $balance_;
                    //     }
                    //     $balance_ = $balance_ - $totalTuition;
                    //     $payment = new PaymentScheduleTemplate;
                    //     $payment->fill([
                    //         'due_date' => ($i == 0) ? $startdate->format('Y-m-d') : $startdate->addWeek()->format('Y-m-d'),
                    //         'payable_amount' => $payable
                    //     ]);
                    //     $payment->offerLetter()->associate($offerLetter);
                    //     $payment->course_detail()->associate($course);
                    //     $payment->user()->associate(\Auth::user());
                    //     $payment->save();
                    // }
                }


                /* for ($i = 0; $i < $monthly; $i++) {
                    $payment = new PaymentScheduleTemplate;

                    if ($key == 0) {
                        $curr_balance = isset($curr_balance) ? $curr_balance : $offerLetter->fees->balance_due;
                        $noRound = round($offerLetter->fees->balance_due / $monthly);
                        $round = round($offerLetter->fees->balance_due / $monthly, -2);

                        $checkNegative = number_format($curr_balance - ($offerLetter->fees->balance_due / $monthly));

                        if ($checkNegative > 0) {
                            $roundPayable = $noRound > $round ? $round + 100 : $round;
                        } else {
                            $roundPayable = $curr_balance;
                        }
                        $payment->fill([
                            'due_date' => ($i == 0) ? $startDuration->addWeek($weekCount)->format('Y-m-d') : $startDuration->addMonth()->format('Y-m-d'),
                            'payable_amount' => (float) $roundPayable
                        ]);
                        $payable = $roundPayable;
                        $curr_balance = (isset($curr_balance) ? floor((int) $curr_balance - (int) $payable) : (int) $offerLetter->fees->balance_due);
                    }
                    $payment->offerLetter()->associate($offerLetter);
                    $payment->course_detail()->associate($course);
                    $payment->user()->associate(Auth::user());
                    if ($roundPayable != 0) {
                        $payment->save();
                    }
                } */
                break;
            }
            // dd($payment)
            DB::commit();
        } catch (\Throwable $th) {
            DB::rollback();
            throw $th;
        }
    }

    public function course_detail($offer_letter)
    {
        $cd = OfferLetterCourseDetail::with('offer_letter.fees', 'offer_letter', 'package.detail.course.detail', 'course_matrix.detail', 'payment_template.payment_detail')->has('offer_letter')->where('offer_letter_id', $offer_letter)->get();

        // dd($cd);
        $course_detail = [];

        $agents = AgentDetail::all();

        foreach ($cd as $key => $course) {


            // check package or non-packacge
            if ($course->package != null) {
                $name = $course->package->detail[$key]->course->detail->name;
            } else {
                $name = $course->course_matrix->detail->name;
            }

            if ($key == 0) {
                $course_tuition = $course->offer_letter->fees->total_course_fee_due;
                $balance = $course->offer_letter->fees->balance_due;
                $inital_payment =  $course->offer_letter->fees->payment_required;
            } else {
                $course_tuition = $course->tuition_fees;
                $balance = $course->tuition_fees;
                $inital_payment =  0;
            }
            $detail = [];
            foreach ($course->payment_template as $payment) {
                $detail[] = [
                    'id'    => $payment->id,
                    'due_date' => $payment->due_date->format('d/m/Y'),
                    'payable_amount' => number_format($payment->payable_amount, 2),
                    'amount_paid'   => $payment->amount_paid,
                    'prededucted'   => $payment->prededucted_com,
                    'date_paid'     => $payment->last_payment_date
                ];
            }
            $course_detail[] = [
                'id' => $course->id,
                'student_id' => $course->offer_letter->student_id,
                'name'  => $course->offer_letter->student->party->name,
                'email'  => $course->offer_letter->student_details->email_personal,
                'course_code' => $course->course_code,
                'course_name' => $name,
                'course_tuition' => number_format($course_tuition, 2),
                'balance'   => number_format($balance, 2),
                'initial_payment' => number_format($inital_payment, 2),
                'address' => $course->offer_letter->student_details->current_address,
                'duration' => $course->week_duration,
                'shore_type' => $course->offer_letter->shore_type,
                'course_start_date' =>  Carbon::parse($course->course_start_date)->format('d/m/Y'),
                'course_end_date' =>  Carbon::parse($course->course_end_date)->format('d/m/Y'),
                'agent' => $course->offer_letter->agent != null ? $course->offer_letter->agent->party->name : '',
                'payments' => $detail,
                'status'    => $course->status->description,
                'status_id'    => $course->status->id,
                'order'    => $course->order,
                'agents'   => $agents,
            ];
        }

        return $course_detail;
    }
    public function show_course_detail($offer_data)
    {
        // dd($offer_data);
        $ol = OfferLetter::where('id', $offer_data)->first();
        $offer_status = OfferLetterStatus::whereIn('id', [1, 2, 4, 5, 7, 8])->get();
        $payment_status = PaymentStatus::all();
        $comm_status = CommissionStatus::all();
        $student = Student::with('party.person')->where('student_id', $ol->student_id)->first();

        \JavaScript::put(
            [
                'offer' => $offer_data,
                'payment_status' => $payment_status,
                'comm_status'   => $comm_status,
                'offer_status' => $offer_status,
                'student_id'    => $ol->student_id,
                'student'       =>  $student,
            ]
        );
        return view('offer-letter.course_detail');
    }
    public function course_detail_update($id, Request $request)
    {
        try {
            DB::beginTransaction();
            $course = OfferLetterCourseDetail::find($id);
            if ($course->order != 1) {
                if ($request->status == '2' || $request->status == '3') {
                    if ($course->payment_template->isEmpty()) {
                        $this->updatePayment($course);
                    }
                }
            }
            $course->status_id = $request->status;
            $course->save();
            DB::commit();
            return response()->json(['status' => 'success']);
        } catch (\Throwable $th) {
            DB::rollback();
            return response()->json(['status' => 'error', 'message' => ' ERROR - ' . $th->getMessage()]);

            //throw $th;
        }
        dump($course);
    }

    public function previewOfferLetterPdf($ol)
    {
        set_time_limit(500); //
        $org = TrainingOrganisation::first();
        $offerData = OfferLetter::with('agent.detail', 'student.party.person', 'course_details.course', 'course_details.course_matrix', 'course_details.package.dlvr_location', 'course_details.payment_template', 'student_details', 'fees')->where('id', $ol)->first()->toArray();
        $offerData['location'] = $offerData['course_details'][0]['package'] ?  $offerData['course_details'][0]['package']['dlvr_location']['train_org_dlvr_loc_name']  : $offerData['course_details'][0]['course_matrix']['location'];
        // $cp = CourseProspectus::where('course_code',$offerData['course_details']['0']['course_code'])->whereIn('location',[$offerData['location']])->first();
        $cp = CourseProspectus::where('course_code', $offerData['course_details']['0']['course_code'])->where('location', 'LIKE', '%' . $offerData['location'] . '%')->first();
        $offerData['dlvr_loc']  = TrainingDeliveryLoc::with('state')->where('train_org_dlvr_loc_id', $cp->train_org_dlvr_loc_id)->first()->toArray();
        
        foreach ($offerData['course_details'][0]['payment_template'] as $template) {
            $breakdown[] = [
                'due_date' => Carbon::parse($template['due_date'])->format('d/m/Y'),
                'payable' => $template['payable_amount'],
                'receipt' => ''
            ];
            // dump($template);
        }
        // dd($offerData);
        if (count($breakdown) > 15) {
            $breakdowns = array_chunk($breakdown, 17);
            $page = count($breakdowns);
        } else {
            // $breakdowns = $offerData['payment_sched'][0];
            $breakdowns[0] = $breakdown;
            $page = 1;
        }
        $signed = true;
        // if($offer)
        // dd($offerData);
        if (env('APP_NAME') == 'Phoenix') {
            if ($offerData['student']['student_type_id'] == 2) {
                return \PDF::loadView('enrolment.pca.offer-letter-enrolment-acceptance-agreement-domestic-pdf', compact('offerData', 'signed', 'org'))->setPaper('A4')->stream($offerData['student']['party']['name']. '- Offer Letter and Enrolment Acceptance Agreement.pdf');
            }
            return \PDF::loadView('enrolment.pca.offer-letter-enrolment-acceptance-agreement-pdf', compact('offerData', 'signed', 'org'))->setPaper('A4')->stream($offerData['student']['party']['name'] . '- Offer Letter and Enrolment Acceptance Agreement.pdf');
        } else if (env('APP_NAME') == 'CEA') {
            // return \PDF::loadView('enrolment.cea.offer-letter-enrolment-acceptance-agreement-pdf', compact('offerData', 'signed', 'org'))->setPaper('A4')->stream('test.pdf');
            $path = storage_path() . '/app/public/offer_letter/';
            \PDF::loadView('enrolment.cea.offer-letter-enrolment-acceptance-agreement-pdf', compact('offerData', 'signed', 'org'))->setPaper('A4')->save($path . '/dynamicOfferletter.pdf');

            $pdf = new \PDFMerger;

            $pdf->addPDF(storage_path() . '/app/public/offer_letter/dynamicOfferletter.pdf', '1');
            $pdf->addPDF(storage_path() . '/app/public/offer_letter/offerLetterStaticPages.pdf', '1-2');
            $pdf->addPDF(storage_path() . '/app/public/offer_letter/dynamicOfferletter.pdf', '2');
            $pdf->addPDF(storage_path() . '/app/public/offer_letter/offerLetterStaticPages.pdf', '3-24');
            $pdf->addPDF(storage_path() . '/app/public/offer_letter/dynamicOfferletter.pdf', '3-4');

            $pdf->merge('browser', storage_path() . '/app/public/offer_letter/offer letter and enrolment acceptance agreement.pdf');
        }else{

        }

        // if ($offerData['student']['student_type_id'] == 2) {
        //     return \PDF::loadView('enrolment.pca.offer-letter-enrolment-acceptance-agreement-domestic-pdf', compact('offerData', 'signed'))->setPaper('A4')->stream('test.pdf');
        // }
        // return \PDF::loadView('enrolment.pca.offer-letter-enrolment-acceptance-agreement-pdf', compact('offerData', 'signed'))->setPaper('A4')->stream('test.pdf');
        // return \PDF::loadView('offer-letter.pdf-template-v2', compact('offerData', 'breakdowns', 'page'))->setPaper(array(0, 0, 595, 925))->stream($offerData['student']['party']['name'] . ' Offer Letter and Enrolment Conditional Acceptance.pdf');
    }
    public function previewCourseDetailPdf($id)
    {
        $offerData = OfferLetterCourseDetail::find($id);
        if (!$offerData->payment_template->isEmpty()) {
            foreach ($offerData->payment_template as $template) {
                $breakdowns[] = [
                    'due_date' => $template->due_date->format('d/m/Y'),
                    'payable'  => $template->payable_amount,
                    'receipt'   => ''
                ];
            }
            $page = 1;
        }
        return \PDF::loadView('offer-letter.pdf-template-course-details', compact('offerData', 'breakdowns', 'page'))->setPaper(array(0, 0, 595, 925))->stream($offerData->offer_letter->student->party->name . ' Offer Letter and Enrolment Conditional Acceptance.pdf');
    }

    public function store_checklist(Request $request, $student)
    {
        // dd($request->all());
        $data = $request->all();
        try {
            DB::beginTransaction();
            $cl = StudentChecklist::updateOrCreate(['student_id' => $student], [
                'checklist' => json_encode($data)
            ]);
            DB::commit();
            return response()->json(['status' => 'success']);
        } catch (\Throwable $th) {
            DB::rollback();

            return response()->json(['status' => 'error', 'message' => $th->getMessage()]);
        }
    }

    public function coursedetail(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'funded_student_course_id' => 'required',
        ]);

        if ($validator->fails()) {
            return response()->json(['status' => 'errors', 'errors' => $validator->messages()]);
        }
        $checker = FundedStudentCourseDetail::where('funded_student_course_id', $request->funded_student_course_id)->first();
        if ($checker !== null) {
            $checker->update($request->all());
            return response()->json(['status' => 'success']);
        } else {
            $ed = new FundedStudentCourseDetail;
            try {
                DB::beginTransaction();
                $ed->fill($request->all());
                $ed->funded_student_course()->associate($request->funded_student_course_id);
                $ed->save();
                DB::commit();
                return response()->json(['status' => 'success']);
            } catch (\Throwable $th) {
                //throw $th;
                DB::rollback();
                return response()->json(['status' => 'errors', 'errors' => $th->getMessage()]);
            }
        }
    }

    public function enrolment_email(OfferLetter $id)
    {

        $org = TrainingOrganisation::first();

        // $emailsTo = ['niwang101@gmail.com'];

        // $admin_emails = ['phoenixcollegeaustralia@gmail.com', 'admin@phoenixcollege.edu.au','xyshain@gmail.com'];
        // $admin_emails = ['admin@phoenixcollege.edu.au', 'xyshain@gmail.com', 'niwang101@gmail.com'];

        $admin_emails = EmailAutomation::where('addOn', 'offer_letter')->pluck('email');
        $enrolmentForm = $id->enrolment_pack->enrolment_form;
        if (isset($enrolmentForm['contact_details_email'])) {
            $admin_emails[] = $enrolmentForm['contact_details_email'];
        }
        if ($id->process_id != null) {
            $payment = '1000.00';
            if ($id->fees->initial_payment_amount == '0') {
                $payment =  $id->fees->payment_required;
            } else {
                $payment = $id->fees->initial_payment_amount;
            }
            $send = new EmailSendingController;
            $ptrLink = url('pre-training-review/process/' . $id->process_id);
            $reviewLink = url('enrolment-process/' . $id->process_id);
            $emailsTo = [];
            // $emailsTo[] = 'niwang101@gmail.com';
            $emailsTo[] = $id->student_details->email_personal;
            

            $this->generatePDF($id->process_id);
            $path = [['path' => storage_path() . '/app/public/offer_letter/' . $id->student_id . '/' . $id->student->party->name . ' - offer letter acceptance and agreement.pdf']];
            $content = '<b>Dear ' . $id->student->party->name . ',</b><br><br>On behalf of the team at Phoenix College of Australia, I would like to take this opportunity to congratulate you for getting the offer letter to study your desired course. The digital copy of your Offer letter and Enrolment Acceptance Agreement for the course is ready for you to review and to sign. <br><br>Please complete the Pre-training review here on the link <a href="' . $ptrLink . '">(PTR)</a>. The information collected from this review will help PCA to remove barriers within learning and assessment processes and practices, which place individuals with specific needs and appropriateness of course for applicant. <br><br>Please review your offer letter and acceptance agreement on the ( <a href="' . $reviewLink . '">link</a> ) and sign to confirm that the information in the offer letter and acceptance agreement is true and correct.<br><br>Electronic Transactions (Queensland) Act 2001 establishes the regulatory framework for transactions to be completed electronically. This form requires you to tick the boxes and put your name as a electronic signature before submission. By ticking the box and putting the name in signature panel, you indicate your approval of the contents of this electronic offer letter and acceptance agreement.<br><br>Please deposit the AUD ' . $payment . ' into the following account and send the receipt along with this offer letter and acceptance agreement.<br><br>Account name: Phoenix College of Australia<br>BSB: 033-501<br>Account number: 289843<br><br><br>Thank you very much and I wish you good luck for your study at Phoenix College of Australia.<br><br><br>Regards<br><br><b>Admin Team</b><br>Phoenix College of Australia<br>RTO NO: 45633 | CRICOS CODE: 03871F';

            // EmailTemplate
            // echo $content;
            // exit();

            $s = $send->send_automate('Pre-Training Review, Offer letter and Enrolment Acceptance Agreement', $content, ['Phoenix College of Australia' => $org->email_address], $emailsTo, $path, $admin_emails);
            // $s = $send->send_automate('PCA Enrolment Application', $content, ['Phoenix College of Australia' => 'constant.claro@gmail.com'], ['konstant.claro@gmail.com']);

            return response()->json($s);
        }
    }

    public function generatePDF($process_id)
    {
        $org = TrainingOrganisation::first();
        // dd($org);
        $ef = EnrolmentPack::where('process_id', $process_id)->first();
        // dd($ef);
        // $ol = OfferLetter::where('student_id')

        $offerData = OfferLetter::with('agent.detail', 'student.party.person', 'course_details.course', 'course_details.course_matrix', 'course_details.package.dlvr_location', 'course_details.payment_template', 'student_details', 'fees')->where('student_id', $ef->student_id)->first()->toArray();
        $offerData['location'] = $offerData['course_details'][0]['package'] ?  $offerData['course_details'][0]['package']['dlvr_location']['train_org_dlvr_loc_name']  : $offerData['course_details'][0]['course_matrix']['location'];
        // $location = explode(',',$offerData['location']);
        $cp = CourseProspectus::where('course_code', $offerData['course_details']['0']['course_code'])->where('location', 'LIKE', '%' . $offerData['location'] . '%')->first();
        // dd($cp);
        $offerData['dlvr_loc']  = TrainingDeliveryLoc::with('state')->where('train_org_dlvr_loc_id', $cp->train_org_dlvr_loc_id)->first()->toArray();
        // dd($offerData);
        $breakdown = [];
        foreach ($offerData['course_details'][0]['payment_template'] as $template) {
            $breakdown[] = [
                'due_date' => Carbon::parse($template['due_date'])->format('d/m/Y'),
                'payable' => $template['payable_amount'],
                'receipt' => ''
            ];
            // dump($template);
        }
        if (count($breakdown) > 15) {
            $breakdowns = array_chunk($breakdown, 17);
            $page = count($breakdowns);
        } else {
            // $breakdowns = $offerData['payment_sched'][0];
            $breakdowns[0] = $breakdown;
            $page = 1;
        }
        // dd($offerData);
        $signed = false;
        // dd(env('APP_NAME'));
        // dd(env('APP_NAME')); 
        // dd($offerData);


        if (env('APP_NAME') == 'Phoenix') {
            $path = storage_path() . '/app/public/offer_letter/' . $offerData['student_id'] . '/';
            if (!File::isDirectory($path)) {
                File::makeDirectory($path, 0777, true, true);
            }
            if ($ef->student_type == 2) {
                \PDF::loadView('enrolment.pca.offer-letter-enrolment-acceptance-agreement-domestic-pdf', compact('offerData', 'signed', 'org'))->setPaper('A4')->save($path . $offerData['student']['party']['name'] . ' - offer letter acceptance and agreement.pdf');
            }
            \PDF::loadView('enrolment.pca.offer-letter-enrolment-acceptance-agreement-pdf', compact('offerData', 'signed', 'org'))->setPaper('A4')->save($path . $offerData['student']['party']['name'] . ' - offer letter acceptance and agreement.pdf');
        } else {
            $path = storage_path() . '/app/public/offer_letter/' . $offerData['student_id'] . '/';
            if (!File::isDirectory($path)) {
                File::makeDirectory($path, 0777, true, true);
            }
            $path = storage_path() . '/app/public/offer_letter/';
            \PDF::loadView('enrolment.cea.offer-letter-enrolment-acceptance-agreement-pdf', compact('offerData', 'signed', 'org'))->setPaper('A4')->save($path . '/dynamicOfferletter.pdf');

            $pdf = new \PDFMerger;

            $pdf->addPDF(storage_path() . '/app/public/offer_letter/dynamicOfferletter.pdf', '1');
            $pdf->addPDF(storage_path() . '/app/public/offer_letter/offerLetterStaticPages.pdf', '1-2');
            $pdf->addPDF(storage_path() . '/app/public/offer_letter/dynamicOfferletter.pdf', '2');
            $pdf->addPDF(storage_path() . '/app/public/offer_letter/offerLetterStaticPages.pdf', '3-24');
            $pdf->addPDF(storage_path() . '/app/public/offer_letter/dynamicOfferletter.pdf', '3-4');
            $pdf->merge('file', storage_path() . '/app/public/offer_letter/' . $offerData['student_id'] . '/' . $offerData['student']['party']['name'] . ' offer letter and enrolment acceptance agreement.pdf');
        }
    }

    public function create_pdf_again(OfferLetter $id){
        try {

       
        $signed = true;
        $offerData = $id->load(['agent.detail', 'student.party.person', 'course_details.course', 'course_details.course_matrix', 'course_details.package.dlvr_location', 'course_details.payment_template', 'student_details', 'fees'])->toArray();
        $offerData['location'] = $offerData['course_details'][0]['package'] ?  $offerData['course_details'][0]['package']['dlvr_location']['train_org_dlvr_loc_name']  : $offerData['course_details'][0]['course_matrix']['location'];
         $cp = CourseProspectus::where('course_code',$offerData['course_details']['0']['course_code'])->whereIn('location',[$offerData['location']])->first();
        $offerData['dlvr_loc']  = TrainingDeliveryLoc::with('state')->where('train_org_dlvr_loc_id', $cp->train_org_dlvr_loc_id)->first()->toArray();
       
        // dd($offerData);
        foreach ($offerData['course_details'][0]['payment_template'] as $template) {
            $breakdown[] = [
                'due_date' => Carbon::parse($template['due_date'])->format('d/m/Y'),
                'payable' => $template['payable_amount'],
                'receipt' => ''
            ];
            // dump($template);
        }
        if (count($breakdown) > 15) {
            $breakdowns = array_chunk($breakdown, 17);
            $page = count($breakdowns);
        } else {
            // $breakdowns = $offerData['payment_sched'][0];
            $breakdowns[0] = $breakdown;
            $page = 1;
        }
        $path = storage_path() . '/app/public/student/new/attachments/' . $id->student_id;

        if (!File::isDirectory($path)) {
            File::makeDirectory($path, 0777, true, true);
        }

        // dd($pdf);
        $hashFileName = 'offer-letter-and-enrolment-acceptance-agreement';
        if ($id->student->student_type_id == 2) {
            \PDF::loadView('enrolment.pca.offer-letter-enrolment-acceptance-agreement-domestic-pdf', compact('offerData', 'signed'))->setPaper('A4')->save($path . '/' . $hashFileName . '.pdf');
        } else {
            \PDF::loadView('enrolment.pca.offer-letter-enrolment-acceptance-agreement-pdf', compact('offerData', 'signed'))->setPaper('A4')->save($path . '/' . $hashFileName . '.pdf');
        }


        $pdf = Storage::size('/public/student/new/attachments/' . $id->student_id . '/' . $hashFileName . '.pdf');

        $existattachment = StudentAttachment::where('student_id', $id->student_id)->where('_input', 'offer_letter')->first();

        if ($existattachment == null) {
            $studentAttachment = new StudentAttachment([
                'name'      => 'offer-letter-and-enrolment-acceptance-agreement.pdf',
                'hash_name' => 'offer-letter-and-enrolment-acceptance-agreement',
                'size'      => $pdf,
                'mime_type' => 'application/pdf',
                'ext'       => 'pdf',
                '_input'       => 'offer_letter',
            ]);
            // associated user
            $studentAttachment->user()->associate(\Auth::user());
            $studentAttachment->student()->associate($id->student_id);
            $studentAttachment->save();
            $studentAttachment->path_id = $id->student_id;
            $studentAttachment->update();
        }
        return response()->json(['status'=>'success'],200);
        } catch (\Throwable $th) {
            throw $th;
        }

    }

    public function emailAgent($offerletter){

        $ol_details = $offerletter->course_details;
        $agent = AgentDetail::where('id', $offerletter->agent_id)->first();
        $student = Student::with('party.person')->where('student_id', $offerletter->student_id)->first();

        try{
            DB::beginTransaction();

            $org = TrainingOrganisation::first();
            $send = new EmailSendingController;
            $emailsTo = [];
            if(isset($agent)){
                $name = $agent->agent_name;
                if(isset($agent->email)){
                    $emailsTo[] = $agent->email;
                }else{   
                    return response()->json(['status'=>'error','message'=>'Agent email not found']);
                }
            }else{
                return response()->json(['status'=>'error','message'=>'Agent not found']);
            }
            $payment_schedule = $offerletter->payment_sched;
            $tbody = '';
            foreach($payment_schedule as $key=>$ps){
                $mont = $key+1;
                $tr = '
                    <tr>
                        <td style="text-align:left; border: 1px solid #ddd;padding: 8px;">
                            '.$mont.'
                        </td>
                        <td style="text-align:left; border: 1px solid #ddd;padding: 8px;">
                            '.number_format($ps['payable_amount'],2).'
                        </td>
                        <td style="text-align:left; border: 1px solid #ddd;padding: 8px;">
                            '.Carbon::parse($ps['due_date'])->format('d/m/Y').'
                        </td>
                        <td style="text-align:left; border: 1px solid #ddd;padding: 8px;">
                            '.number_format($ps['commission'],2).'
                        </td>
                    </tr>
                ';
                $tbody .= $tr;
            }
            $table = '<table style="border-collapse: collapse;
        width: 100%;">
                    <thead>
                        <tr>
                            <th style="text-align:left; border: 1px solid #ddd;
                            padding: 8px;">Month #</th>

                            <th style="text-align:left; border: 1px solid #ddd;
                            padding: 8px;">Amount Due</th>

                            <th style="text-align:left; border: 1px solid #ddd;
                            padding: 8px;">Due Date</th>

                            <th style="text-align:left; border: 1px solid #ddd;
                            padding: 8px;">Commission</th>
                        </tr>
                    </thead>
                    <tbody>
                        '.$tbody.'
                    </tbody>
                </table>';
            // $link = 'https://agentportal.vorx.com.au/student/'.$offerletter->student_id.'/payment-details/'.$ol_details->course_code;

            $link = 'https://'.ENV('SANCTUM_STATEFUL_DOMAINS').'/student/'.$offerletter->student_id.'/payment-details/'.$offerletter->course_code;
            $content = '<b>Dear ' . $name . ',</b><br><br>'.$student->party->name.' has been enrolled in '.$offerletter->course_code.' course.<br><br> Payment Plan <br>'.$table.'<br><br> Please visit <a href="'.$link.'" target="_blank" data-saferedirecturl="'.$link.'">Agent Portal</a><br>Regards,<br><br><b>Admin Team</b>';

            $s = $send->send_automate('Newly Enrolled Student', $content, [$org->training_organisation_name => $org->email_address], $emailsTo,[],[]);

            if($s['status'] !== 'success'){
                DB::rollback();
                return response()->json(['status'=>'error','message'=>'Email agent error']);
            }
            // DB::commit();
            // return response()->json(['status'=>'success']);
        }catch (\Throwable $th) {
            DB::rollback();
            throw $th;
        }
    }
}
