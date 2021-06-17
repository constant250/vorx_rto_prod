<?php

namespace App\Http\Controllers\API\Student;

use App\Http\Controllers\Controller;
use App\Models\AgentTempUpdate;
use App\Models\AvtPostcode;
use App\Models\AvtStateIdentifier;
use App\Models\FundedStudentPaymentDetails;
use App\Models\PaymentScheduleTemplate;
use App\Models\Student\Student;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class StudentController extends Controller
{
    //
    public function index($username){
        // $user = User::where('username',$username)->first();
        $user = Auth::user();
        // dd($user);
        $students = [];
        if($user->id == 1){
            $rstudent = Student::orderBy('id','desc')->get();
            foreach($rstudent as $student){
                $funded_course = $student->funded_course->load('course');

                $course = [];
                foreach($funded_course as $fcourse){
                    $balance = 0;
                    $status_color = 'positive';
                    if($fcourse->status_id == 1){
                        $status_color = 'warning';
                    }elseif($fcourse->status_id == 2 || $fcourse->status_id == 3 || $fcourse->status_id == 4){
                        $status_color = 'positive';
                    }elseif($fcourse->status_id == 6){
                        $status_color = 'info';
                    }else{
                        $status_color = 'negative';
                    }
                    $balance = (float)$fcourse->course_fee - $fcourse->payment_details->sum('amount');
                    if($fcourse->course  == null){
                        if($fcourse->course_code == '@@@@'){
                            $course[] = [
                                'code' => '',
                                'name' => 'Unit of Compentency',
                                'status' => $fcourse->status->description,
                                'status_color' => $status_color,
                                'payment_details' => number_format($fcourse->payment_details->sum('amount'),2),
                                'course_fee' => $fcourse->course_fee,
                                'balance' => $balance > 0 ? number_format($balance,2) : 0.00
                                
                            ];
                        }else{
                            
                            $course[] = [
                                'code' => $fcourse->course_code,
                                'name' => 'NO COURSE NAME',
                                'status' => $fcourse->status->description,
                                'status_color' => $status_color,
                                'payment_details' => number_format($fcourse->payment_details->sum('amount'),2),
                                'course_fee' => $fcourse->course_fee,
                                'balance' =>  $balance > 0 ? number_format($balance,2) : 0.00
                            ];
                        }
                        
                    }else{
                        $course[] = [
                            'code' => $fcourse->course_code,
                            'name' => $fcourse->course->name,
                            'status' => $fcourse->status->description,
                            'status_color' => $status_color,
                            'payment_details' => number_format($fcourse->payment_details->sum('amount'),2),
                            'course_fee' => $fcourse->course_fee,
                            'balance' =>  $balance > 0 ? number_format($balance,2) : 0.00
                        ];

                    }
                }
                $students[] = [
                    'student_id' => $student->student_id,
                    'name'=> $student->party->name,
                    'type'=> $student->type->type,
                    'courses' => $course
                ];
            } 
        }else{
            $agent = $user->agent_details;
            $offer_letter = $agent->offer_letters->load('student');
            
            foreach($offer_letter as $key=>$offer){
                $funded_course = $offer->student->funded_course->load('course');
                $course = [];
                foreach($funded_course as $fcourse){
                    $balance = 0;
                    $status_color = 'positive';
                    $balance = (float)$fcourse->course_fee - $fcourse->payment_details->sum('amount');
                    if($fcourse->status_id == 1){
                        $status_color = 'warning';
                    }elseif($fcourse->status_id == 2 || $fcourse->status_id == 3 || $fcourse->status_id == 4){
                        $status_color = 'positive';
                    }else{
                        $status_color = 'negative';
                    }
                    if($fcourse->course  == null){
                        $course[] = [
                            'code' => $fcourse->course_code,
                            'name' => 'NO COURSE NAME',
                            'status' => $fcourse->status->description,
                            'status_color' => $status_color,
                            'payment_details' => number_format($fcourse->payment_details->sum('amount'),2),
                            'course_fee' => $fcourse->course_fee,
                            'balance' =>  $balance > 0 ? number_format($balance,2) : 0.00
                        ];
                    }else{
                        $course[] = [
                            'code' => $fcourse->course_code,
                            'name' => $fcourse->course->name,
                            'status' => $fcourse->status->description,
                            'status_color' => $status_color,
                            'payment_details' => number_format($fcourse->payment_details->sum('amount'),2),
                            'course_fee' => $fcourse->course_fee,
                            'balance' =>  $balance > 0 ? number_format($balance,2) : 0.00
                        ];

                    }
                }
                $students[] = [
                    'student_id' => $offer->student->student_id,
                    'name'=> $offer->student->party->name,
                    'type'=> $offer->student->type->type,
                    'courses' => $course
                ];
            }
        }
        
        return $students;
    }

    public function show(Student $student){
        // $data = [];
        $person = $student->party->person;
       
        $funded_detail = $student->funded_detail()->select([
                    'unique_student_id',
                    'highest_school_level_completed_id',
                    'indigenous_status_id',
                    'location',
                    'language_id',
                    'labour_force_status_id',
                    'country_id',
                    'disability_flag',
                    'disability_ids',
                    'prior_educational_achievement_ids',
                    'prior_educational_achievement_flag',
                    'at_school_flag',
                    'institute',
                    'statistical_area_level_1_id',
                    'statistical_area_level_2_id',
                    'aiss_check_date',
                    'year_completed',
                    'verified',
                    'verified_by',
                    'verified_date',
                ])->first();
        $funded_detail->disability_ids = $funded_detail->disabilityvalue;
        $funded_detail->prior_educational_achievement_ids = $funded_detail->prioreducationvalue;
        $funded_detail->language_id = $funded_detail->languageapi;
        $funded_detail->indigenous_status_id = $funded_detail->indigenous;
        $funded_detail->country_id = $funded_detail->country;
        $funded_detail->highest_school_level_completed_id = $funded_detail->highestschool;
        $funded_detail->labour_force_status_id = $funded_detail->labour;

        if($funded_detail->disability_flag == null){
            if($funded_detail->disability_ids == null){
                $funded_detail->disability_flag = false;
            }else{
                $funded_detail->disability_flag = true;
            }
            $funded_detail->disability_flag = false;
        }else{
            if($funded_detail->disability_flag == 'N'){
                $funded_detail->disability_flag = false;
            }else{
                $funded_detail->disability_flag = true;
            }
        }
        if($funded_detail->at_school_flag == null){
            $funded_detail->at_school_flag = false;
        }else{
            if($funded_detail->at_school_flag == 'N'){
                $funded_detail->at_school_flag = false;
            }else{
                $funded_detail->at_school_flag = true;
            }
        }
        if($funded_detail->prior_educational_achievement_flag == null){
            $funded_detail->prior_educational_achievement_flag = false;
        }else{
            if($funded_detail->prior_educational_achievement_flag == 'N'){
                $funded_detail->prior_educational_achievement_flag = false;
            }else{
                $funded_detail->prior_educational_achievement_flag = true;
            }
        }
        $contact_detail = $student->contact_detail()->select([
                    'addr_suburb',
                    'addr_postal_delivery_box',
                    'addr_street_name',
                    'addr_street_num',
                    'addr_flat_unit_detail',
                    'addr_building_property_name',
                    'postcode',
                    'state_id',
                    'phone_home',
                    'phone_work',
                    'phone_mobile',
                    'email',
                    'email_at',
                    "emer_name",
                    "emer_address",
                    "emer_telephone",
                    "emer_relationship"
                ])->first();
        $contact_detail->addr_suburb = $contact_detail->suburb;
        $visa = $student->visa_details()->select([
                'nationality',
                'passport_number',
                'issue_date',
                'expiry_date',
                'visa_type',
                'subclass',
                'expiry_date_visa_type',
                'applied_for_au_residency',
                'study_rights'
            ])->first();    
        $visa->visa_type = $visa->visavalue;
    
      

        $data = [
            'firstname'     => $person->firstname,
            'middlename'    => $person->middlename,
            'lastname'      => $person->lastname,
            'dob'           => Carbon::parse($person->date_of_birth)->format('d/m/Y'),
            'gender'        => $person->gender,
            'prefix'        => $person->prefix,
            'funded_detail' => $funded_detail,
            'contact'       => $contact_detail,
            'visa'          => $visa,
        ];
        return $data;
    }

    public function course(Student $student){
        
        $data = $student->load('funded_course.status','funded_course.offer_detail','funded_course.course_details');
        $course = [];
        foreach($data->funded_course as $funded_course){
            $course_fee_type = '';
            switch ($funded_course->course_fee_type) {
                case 'C':
                    $course_fee_type = 'Concessional';
                    break;
                case 'NC':
                    $course_fee_type = 'Non-Concessional';
                    break;
                default:
                    $course_fee_type = 'Full Fee';
                    break;
            }
            if($funded_course->course_code == '@@@@'){
                $d = [
                    'code' => $funded_course->course_code,
                    'name' => 'Unit of Competency',
                    'status' => $funded_course->status != null ?  $funded_course->status->description : '',
                    'start_date' => Carbon::parse($funded_course->start_date)->format('d/m/Y'), 
                    'end_date' => Carbon::parse($funded_course->end_date)->format('d/m/Y'), 
                    'eligibility' => $funded_course->eligibility == 'E' ? 'Eligible' : 'Not Eligible',
                    'fee' => $funded_course->course_fee, 
                    'course_fee_type' => $course_fee_type, 
                    'state' => $funded_course->location, 
                    'funding_type' => $funded_course->course_details->fundingtype != null ? $funded_course->course_details->fundingtype->name : '', 
                    'national_funding' => $funded_course->course_details->fund_national != null ? $funded_course->course_details->fund_national->description : '', 
                    'state_funding' => $funded_course->course_details->fund_state != null ? $funded_course->course_details->fund_state->description : '', 
                    'specific_funding' => $funded_course->course_details->specficit_funding != null ? $funded_course->course_details->specficit_funding->description : '', 
                    'study_reasons' => $funded_course->course_details->study_reason != null ? $funded_course->course_details->study_reason->description : '', 
                    'delivery_mode' => $funded_course->course_details->delivery_mode != null ? $funded_course->course_details->delivery_mode->description  : '', 
                    'training_contract' => $funded_course->course_details->training_contract_id, 
                    'apprenticeships' => $funded_course->course_details->client_id_apprenticeships, 
                    'purchasing_contract_id' => $funded_course->course_details->purchasing_contract_id, 
                    'purchasing_contract_schedule_id' => $funded_course->course_details->purchasing_contract_schedule_id, 
                    'associated_course_id' => $funded_course->course_details->associated_course_id, 
                    'full_time_leaning_option' => $funded_course->course_details->full_time_leaning_option, 
                    'full_time_leaning_option' => $funded_course->course_details->full_time_leaning_option, 
                    'outcome_id_national' => $funded_course->course_details->outcome_id_national, 
                    'commencing_course' => $funded_course->course_details->commencing_course != null ? $funded_course->course_details->commencing_course->description : '', 
                ];
            }else{

            
                $d = [
                    'code' => $funded_course->course->code,
                    'name' => $funded_course->course->name,
                    'status' => $funded_course->status != null ?  $funded_course->status->description : '',
                    'start_date' => Carbon::parse($funded_course->start_date)->format('d/m/Y'), 
                    'end_date' => Carbon::parse($funded_course->end_date)->format('d/m/Y'), 
                    'eligibility' => $funded_course->eligibility == 'E' ? 'Eligible' : 'Not Eligible',
                    'fee' => $funded_course->course_fee, 
                    'course_fee_type' => $course_fee_type, 
                    'state' => $funded_course->location, 
                    'funding_type' => $funded_course->course_details->fundingtype != null ? $funded_course->course_details->fundingtype->name : '', 
                    'national_funding' => $funded_course->course_details->fund_national != null ? $funded_course->course_details->fund_national->description : '', 
                    'state_funding' => $funded_course->course_details->fund_state != null ? $funded_course->course_details->fund_state->description : '', 
                    'specific_funding' => $funded_course->course_details->specficit_funding != null ? $funded_course->course_details->specficit_funding->description : '', 
                    'study_reasons' => $funded_course->course_details->study_reason != null ? $funded_course->course_details->study_reason->description : '', 
                    'delivery_mode' => $funded_course->course_details->delivery_mode != null ? $funded_course->course_details->delivery_mode->description  : '', 
                    'training_contract' => $funded_course->course_details->training_contract_id, 
                    'apprenticeships' => $funded_course->course_details->client_id_apprenticeships, 
                    'purchasing_contract_id' => $funded_course->course_details->purchasing_contract_id, 
                    'purchasing_contract_schedule_id' => $funded_course->course_details->purchasing_contract_schedule_id, 
                    'associated_course_id' => $funded_course->course_details->associated_course_id, 
                    'full_time_leaning_option' => $funded_course->course_details->full_time_leaning_option, 
                    'full_time_leaning_option' => $funded_course->course_details->full_time_leaning_option, 
                    'outcome_id_national' => $funded_course->course_details->outcome_id_national, 
                    'commencing_course' => $funded_course->course_details->commencing_course != null ? $funded_course->course_details->commencing_course->description : '', 
                ];
            }
            array_push($course,$d);

           
        }
        return $course;
    }


    public function update(Request $request, Student $student){

        switch ($request->module) {
            case 'info':
                $data = [
                    'firstname' => $request->data['firstname'],
                    'lastname' => $request->data['lastname'],
                    'middlename' => $request->data['middlename'],
                    'date_of_birth' => $request->data['dob'],
                    'prefix' => $request->data['prefix'],
                ];
                $status = $this->student_info($data,$student->student_id);
                if($status == 'success'){
                    return response(['status'=>'success','message'=>'update submitted']);
                }else{
                    return response(['status'=>'error']);
                }
                break;
            case 'contact' : 
                $data = $request->data;
                $status  = $this->contact_info($data,$student->student_id);
                if($status == 'success'){
                    return response(['status'=>'success','message'=>'update submitted']);
                }else{
                    return $status;
                    return response(['status'=>'error']);
                }
                break;
            case 'avetmiss' : 
                $data = $request->data;
                $status  = $this->avetmiss_info($data,$student->student_id);
                if($status == 'success'){
                    return response(['status'=>'success','message'=>'update submitted']);
                }else{
                    return $status;
                    return response(['status'=>'error']);
                }
                break;
            default:
                abort(404,'NOT FOUND');
                # code...
                break;
        }
        // return $request->all();
    }

    private function avetmiss_info($avetmiss,$student_id){
        try {
            //code...
            DB::beginTransaction();
            AgentTempUpdate::updateOrCreate([
                'student_id' => $student_id,
                'module' => 'avetmiss',
                'agent_id'=> Auth::user()->id
            ],['inputs' => $avetmiss]);
            DB::commit();
            return 'success';
        } catch (\Throwable $th) {
            throw $th;
        }
    }

    private function contact_info($contact,$student_id){
        try {
        DB::beginTransaction();
        $suburb = $contact['addr_suburb'];
        $suburb_id = $suburb['value'];
        $postcode = AvtPostcode::where('id', $suburb_id)->first();
        $suburb = $postcode->suburb;
        $state = AvtStateIdentifier::where('state_key', $postcode->state)->first();
        $state_id = $state->id;
        $contact['addr_suburb'] = $suburb;
        $contact['postcode'] = $postcode->postcode;
        $contact['state_id'] = $state->id;

        AgentTempUpdate::updateOrCreate([
            'student_id' => $student_id,
            'module' => 'contact',
            'agent_id'=> Auth::user()->id
        ],['inputs' => $contact]);
        DB::commit();
        return 'success';
        
            //code...
        } catch (\Throwable $th) {
            DB::rollback();
            throw $th;
        }

    }

    private function student_info($student,$student_id){
        try {
            DB::beginTransaction();
            AgentTempUpdate::updateOrCreate([
                'student_id' => $student_id,
                'module' => 'info',
                'agent_id'=> Auth::user()->id
            ],['inputs' => $student]);
            DB::commit();
            return 'success';
        } catch (\Throwable $th) {
            DB::rollback();
            throw $th;
        }
       
    }


    public function payments(Student $student){
        $data = $student->load('funded_course.status','funded_course.offer_detail','funded_course.payment_details','funded_course.payment_sched');
        $course = [];
        foreach($data->funded_course as $funded_course){
            $course_fee_type = '';
            switch ($funded_course->course_fee_type) {
                case 'C':
                    $course_fee_type = 'Concessional';
                    break;
                case 'NC':
                    $course_fee_type = 'Non-Concessional';
                    break;
                default:
                    $course_fee_type = 'Full Fee';
                    break;
            }
            $pl = [];
            $ctr = 1;
            $commission_settings  = $funded_course->commission;

            $fees = $funded_course->offer_detail->offer_letter->fees;
            $nontuition = $fees->materials_fee + $fees->application_fee;
            $balance = $nontuition;
            foreach($funded_course->payment_sched as $psched){
                $commission = 0;
                $prev_balance = $balance;
                if($balance <= 0){
                    if($balance != 0){
                        $prev_balance = $balance;
                        $balance =  $balance + $psched->payable_amount ;
                        if($balance > 0){
                            if($commission_settings != null){
                                if($commission_settings->commission_type == '%'){
                                    if($commission_settings->gst_type == 'not_registered'){
                                        if($commission_settings->gst_status == 0){
                                            $commission =  round($balance * ($commission_settings->commision_value / 100), 2);
                                        }else{
                                            $commission =  round($balance * ($commission_settings->commision_value / 100) - ($balance * ($commission_settings->commision_value / 100) * .10), 2);
                                        }
                                    }else{
                                        if($commission_settings->gst_status == 1){
                                            $commission =  round($balance * ($commission_settings->commision_value / 100), 2);
                                        }else{
                                            $commission =  round($balance * ($commission_settings->commision_value / 100) + ($balance * ($commission_settings->commision_value / 100) * .10), 2);
                                        }
                                    }
                                    
                                }
                            }
                        }
                    }else{
                        $balance =$psched->payable_amount ;
                        if($commission_settings != null){
                            if($commission_settings->commission_type == '%'){
                                if($commission_settings->gst_type == 'not_registered'){
                                    if($commission_settings->gst_status == 0){
                                        $commission =  round($balance * ($commission_settings->commision_value / 100), 2);
                                    }else{
                                        $commission =  round($balance * ($commission_settings->commision_value / 100) - ($balance * ($commission_settings->commision_value / 100) * .10), 2);
                                    }
                                }else{
                                    if($commission_settings->gst_status == 1){
                                        $commission =  round($balance * ($commission_settings->commision_value / 100), 2);
                                    }else{
                                        $commission =  round($balance * ($commission_settings->commision_value / 100) + ($balance * ($commission_settings->commision_value / 100) * .10), 2);
                                    }
                                }
                                
                            }
                        }
                    }
                }else{
                    $balance = $psched->payable_amount - abs($balance);
                    if($balance >= 0 ){
                        $balance = $psched->payable_amount ;
                        if($commission_settings != null){
                            if($commission_settings->commission_type == '%'){
                                if($commission_settings->gst_type == 'not_registered'){
                                    if($commission_settings->gst_status == 0){
                                        $commission =  round($balance * ($commission_settings->commision_value / 100), 2);
                                    }else{
                                        $commission =  round($balance * ($commission_settings->commision_value / 100) - ($balance * ($commission_settings->commision_value / 100) * .10), 2);
                                    }
                                }else{
                                    if($commission_settings->gst_status == 1){
                                        $commission =  round($balance * ($commission_settings->commision_value / 100), 2);
                                    }else{
                                        $commission =  round($balance * ($commission_settings->commision_value / 100) + ($balance * ($commission_settings->commision_value / 100) * .10), 2);
                                    }
                                }
                                
                            }
                        }
                    }
                }
               
                

                $pd = [];
                foreach($psched->payment_detail as $payment_detail){
                    
                    $pd[] = [
                        'id' => $payment_detail->id,  
                        'transaction_code' => $payment_detail->transaction_code,  
                        'payment_date' =>  Carbon::parse($payment_detail->payment_date)->format('d/m/Y'),  
                        'amount' => $payment_detail->amount,  
                        'pre_deduc_comm' => $payment_detail->pre_deduc_comm,  
                        'verified' => $payment_detail->verified,  
                        'note' => $payment_detail->note,  
                    ];
                }
                $pl[]= [
                    'number'             => $ctr++,
                    'id'                 => $psched->id,
                    'name'               => $psched->id,
                    'due_date'           => Carbon::parse($psched->due_date)->format('d/m/Y'),
                    'adjusted_date'      => $psched->adjusted_date != '' ? Carbon::parse($psched->adjusted_date)->format('d/m/Y') : null,
                    'payable_amount'     => $psched->payable_amount,
                    'payment_details'    => $pd,
                    'balance'            => $balance ,
                    'prev_balance'            => $prev_balance ,
                    'commission'         => $commission,
                    'percentage'         => ( $psched->payment_detail->sum('amount') / $psched->payable_amount ) * 100,
                ];
                
            }



            if($funded_course->course_code == '@@@@'){
                $d = [
                    'code'              => $funded_course->course_code,
                    'name'              => 'Unit of Competency',
                    'status'            => $funded_course->status != null ?  $funded_course->status->description : '',
                    'fee'               => $funded_course->course_fee, 
                    'course_fee_type'   => $course_fee_type, 
                    'payment_plan'      => $pl
                ];
            }else{

            
                $d = [
                    'code'              => $funded_course->course->code,
                    'name'              => $funded_course->course->name,
                    'status'            => $funded_course->status != null ?  $funded_course->status->description : '',
                    'fee'               => $funded_course->course_fee, 
                    'course_fee_type'   => $course_fee_type, 
                    'payment_plan'      => $pl
                ];
            }
            array_push($course,$d);

           
        }
        return $course;
    }

    public function paymentsStore(Request $request,$student_id){
        // return $request->all();
        $pl =  PaymentScheduleTemplate::find($request->payment_plan);
        $funded_course =  $pl->funded_student_course_id;
        $offer_detail_course =  $pl->offer_letter_course_detail_id;
        try {
            DB::beginTransaction();
            $funded_payments = new FundedStudentPaymentDetails();
            if($request->deposited_amount > $pl->payable_amount){
                $mpl = PaymentScheduleTemplate::where('funded_student_course_id',$funded_course)->get();
                $balance = $request->deposited_amount - $pl->payable_amount;
                foreach($mpl as $plans){
                    if($plans->payable_amount != $plans->amount_paid){
                        return $plans;
                    }
                }
            }else{
                $data = [
                    'transaction_code' => $request->trxncode,
                    'payment_date' => Carbon::parse($request->colletion_date)->format('Y-m-d'),
                    'amount' => $request->deposited_amount,
                    'student_id'=> $student_id,
                    'pre_deduc_comm' => $request->deducted_commission_amount,
                    'student_course_id' => $funded_course,
                    'payment_schedule_template_id' => $pl->id,
                    'offer_letter_course_detail_id' => $offer_detail_course,
                    'user_id' => Auth::user()->id,
                    'note' => $request->notes,
                ];
    
                $funded_payments->fill($data);
                $funded_payments->save();
            }
            DB::commit();
            //code...
        } catch (\Throwable $th) {
            DB::rollback();
            throw $th;
        } 
        return $funded_course;
    }
}
