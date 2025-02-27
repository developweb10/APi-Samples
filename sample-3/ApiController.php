<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Vitamin;
use App\Models\Formula;
use Illuminate\Support\Facades\Validator;
use App\Models\TreatmentsHistory;
use App\Mail\AppointmentMail;
use App\Models\Appointment;
use App\Models\Treatment;
use App\Models\TreatmentVitamin;
use App\Models\MedicalHistory;
use App\Models\Patient;
use App\Models\FormulaAddOn;
use Illuminate\Support\Facades\Mail;
use Carbon\Carbon;

class ApiController
{
    // Display a listing of the resource.
    public function index()
    {
        $vitamins = Vitamin::orderBy('vitamin_name', 'asc')->get();
        $response = [
            "status"=> 1,
            "message"=> 'Success',
            "data"=> $vitamins
        ];        
        return response()->json($response);
    }

    // Display a listing of the resource.
    public function listAddons()
    {
        $add_ons = Formula::Where('item_type','add_on')->orderBy('id', 'DESC')->get(); 
        if($add_ons) {
            $response = [
                "status"=> 1,
                "message"=> 'Success',
                "data"=> $add_ons
            ];
        }
        else {
            $response = [
                "status"=> 0,
                "message"=> 'Vitamin not found!',
                "data"=> []
            ];
        }
        return response()->json($response);
    }

    // Store a newly created resource in storage.
    public function store(Request $request)
    {
        $Vitamin = Vitamin::create($request->all());
        return response()->json($Vitamin, 201);
    }

    // Display the specified resource.
    public function show(Request $request)
    {
        if($request->vitamin_id && $request->treatment_id) {
            $vitamin = Vitamin::select('vitamins.vitamin_name', 'vitamins.lot_number', 'vitamins.qr_image', 'treatment_vitamin.*')
                ->leftJoin('treatment_vitamin', 'vitamins.id', '=', 'treatment_vitamin.vitamin_id')
                ->where('treatment_vitamin.vitamin_id', $request->vitamin_id)
                ->where('treatment_vitamin.treatment_id', $request->treatment_id)
                ->first();  
        }
        else {
            $vitamin = Vitamin::select('vitamins.vitamin_name', 'vitamins.lot_number', 'vitamins.qr_image', 'treatment_vitamin.*')
                ->leftJoin('treatment_vitamin', 'vitamins.id', '=', 'treatment_vitamin.vitamin_id')
                ->where('treatment_vitamin.vitamin_id', $request->vitamin_id)
                ->first();  
        }

        unset($vitamin->use_by_date); 
        unset($vitamin->created_at);     
        if($vitamin) {
            $vitamin->qr_image = asset('storage/qr-codes/' . $vitamin->qr_image);
            $response = [
                "status"=> 1,
                "message"=> 'Success',
                "data"=> $vitamin
            ];
            return response()->json($response);
        }

        $response = [
            "status"=> 0,
            "message"=> 'Vitamin not found!',
            "data"=> []
        ];
        return response()->json($response);
        
    }

    // Update the specified resource in storage.
    public function update(Request $request, $id)
    {
        try {
            $vitamin = Vitamin::findOrFail($id);
            $vitamin->update($request->all());
        }
        catch(\Exception $e) {
            $vitamin = [
                "status"=> false,
               // "message"=> $e->getMessage(),
                "message"=> 'Error!',
                "data"=> []
            ];
        }
        return response()->json($vitamin);
    }


//// List Treamtments
    public function treatments()
    {
        $treatments = Treatment::orderBy('name', 'asc')->get();
        if($treatments) {
            foreach($treatments as $treatment) {
                $treatment->cost = "";
                $treatment->cost = "";
                $treatment->cost = "";
            }
        }
        return response()->json(['status'=> 1, 'message'=> 'Success', 'treatments'=> $treatments]);
    }    

    // Display the specified resource.
    public function searchVials(Request $request)
    {

        $vitamins = Vitamin::select('vitamins.vitamin_name', 'vitamins.lot_number', 'vitamins.qr_image', 'treatment_vitamin.*')
            ->leftJoin('treatment_vitamin', 'vitamins.id', '=', 'treatment_vitamin.vitamin_id');

        if ($request->has('vitamin_id')) {
            $vitamins->where('treatment_vitamin.vitamin_id', $request->vitamin_id);
        }
        else {

            if ($request->has('vitamin_name')) {
                $vitamins->where('vitamins.vitamin_name', $request->vitamin_name);
            }

            if ($request->has('lot_number')) {
                $vitamins->where('vitamins.lot_number', $request->lot_number);
            }
        }

        $vitamins = $vitamins->get();

        $unsetKeys = ['opened_date','expiry_date','last_used_date','opened_days','use_by_date','last_used_by'];
        if($vitamins) {

            foreach ($vitamins as $vitamin) {

                $vitamin->qr_image = asset('storage/qr-codes/' . $vitamin->qr_image);
                $vitamin->current_status = "";
                $vitamin->created_at = ($vitamin->created_at) ? $vitamin->created_at : "";
                $vitamin->updated_at = ($vitamin->updated_at) ? $vitamin->updated_at : "";            
            }

            foreach ($vitamins as $vitamin) {
                foreach ($unsetKeys as $key) {
                    unset($vitamin->$key);  // Use object notation to access properties                
                }    
            }           

            $response = [
                "status"=> 1,
                "message"=> 'Success',
                'vitamin_id'=> isset($vitamins) ? $vitamins[0]->vitamin_id : 0,
                "checkVial"=> $vitamins,
                
            ];
        }
        else {

            $response = [
                "status"=> 0,
                "message"=> 'Vitamin not found!',
                "checkVial"=> []
            ];
        }

        return response()->json($response);
    } 

    // Display the specified resource.
    public function checkVial(Request $request)
    {   

        $vitamins = Vitamin::select('vitamins.vitamin_name', 'vitamins.lot_number', 'vitamins.qr_image', 'treatment_vitamin.*')
            ->leftJoin('treatment_vitamin', 'vitamins.id', '=', 'treatment_vitamin.vitamin_id')
            // ->where('treatment_vitamin.vitamin_id', $request->id)
            ->where('treatment_vitamin.treatment_id', $request->treatment_id)
            ->get();

        if($vitamins) {

            foreach ($vitamins as $vitamin) {

                $vitamin->qr_image = asset('storage/qr-codes/' . $vitamin->qr_image);
                $vitamin->current_status = "";

                if ($request->patient_id && 
                    $request->patient_id == $vitamin->last_used_by && 
                    Carbon::now()->diffInSeconds(Carbon::parse($vitamin->updated_at)) <= 3600 &&
                    Carbon::now()->diffInSeconds(Carbon::parse($vitamin->updated_at)) >= 0) {
                        $vitamin->current_status = "Ready";
                }

                $vitamin->created_at = ($vitamin->created_at) ? $vitamin->created_at : "";
                $vitamin->updated_at = ($vitamin->updated_at) ? $vitamin->updated_at : "";
            }

            $response = [
                "status"=> true,
                "message"=> 'Success',
                "checkVial"=> $vitamins,
                
            ];
        }
        else {

            $response = [
                "status"=> false,
                "message"=> 'Vitamin not found!',
                "checkVial"=> []
            ];
        }

        return response()->json($response);
    } 

    // Update the specified resource in storage.
    public function scanVitamin(Request $request)
    {

        $treatmentVitamin = TreatmentVitamin::where('vitamin_id', $request->vitamin_id)
                ->where('treatment_id', $request->treatment_id)
                ->firstOrFail();

        // if($treatmentVitamin->expiry_date && strtotime($treatmentVitamin->expiry_date) <= strtotime(date('Y-m-d',strtotime('2024-08-29')))) {
        if($treatmentVitamin->expiry_date && strtotime($treatmentVitamin->expiry_date) <= strtotime(date('Y-m-d'))) {
            $treatmentVitamin->vitamin_status = "expired";
            $treatmentVitamin->save();
            $response = [
                "status"=> false,               
                "message"=> "The selected vitamin has expired.",
                "data"=> []
            ];
            return response()->json($response);                 
        }
        
        if( $treatmentVitamin->doses == $treatmentVitamin->used_total) {
            $treatmentVitamin->vitamin_status = "expired";
            $treatmentVitamin->save();
        }            

        if($treatmentVitamin->vitamin_status && $treatmentVitamin->vitamin_status =='expired') {
            $response = [
                "status"=> false,               
                "message"=> "The selected vitamin has expired.",
                "data"=> []
            ];
            return response()->json($response);                 
        }

        if($treatmentVitamin->left_total < $request->amount_prescribed) {
            $response = [
                "status"=> false,               
                "message"=> "Not enough volume ({$request->amount_prescribed}) left. Try using a smaller or equal volume ({$treatmentVitamin->left_total}).",
                "data"=> []
            ];
            return response()->json($response);                
        }

    //// Select Vitamin
        $vitamin = Vitamin::findOrFail($request->vitamin_id);

        $reduce_volume = $treatmentVitamin->used_total + $request->amount_prescribed;
        $treatmentVitamin->left_total = $treatmentVitamin->doses - $reduce_volume;
        $treatmentVitamin->used_total = $reduce_volume;

        if( $treatmentVitamin->doses == $treatmentVitamin->used_total) {
            $treatmentVitamin->vitamin_status = "expired";
        }

        if(!$treatmentVitamin->opened_date && !$treatmentVitamin->expiry_date) {
            $treatmentVitamin->opened_date = date('Y-m-d');
            $treatmentVitamin->expiry_date = date('Y-m-d',strtotime("+ {$vitamin->opened_days} days",strtotime(date('Y-m-d'))));
        }

        $treatmentVitamin->last_used_date = date('Y-m-d');
        $treatmentVitamin->opened_days = $vitamin->opened_days;
        $treatmentVitamin->last_used_by = $request->patient_id;
        $treatmentVitamin->vitamin_status = "opened";
        $treatmentVitamin->save();        

        $response = [
            "status"=> true,
            "message"=> 'Ready',
            "data"=> $treatmentVitamin
        ];
        return response()->json($response);        
    }       

    // Update the specified resource in storage.
    public function scanInventory(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'treatment_id' => 'required',
            'vitamin_id' => 'required',
        ]); 

        // Check if validation fails
        if ($validator->fails()) {
            return response()->json([
                'error' => 'Validation Error',
                'messages' => $validator->errors(),
            ], 422);
        }

        try {            

            $data = $request->all();
            $data['nurses_assessment'] = array([
                    "reviewed_patient" => true
                ],[
                    "well_appearing" => true
                ],[
                    "emergency_assistance" => true
                ],[
                    "medical_history" => true
                ],[
                    "repeat_clients" => true
                ]
            ); 

            // Create a medical history entry for the patient
            $medicalHistory = Patient::updateOrCreate( [
                    'id' => $request->patient_id
                ],[
                    'date_of_birth' => $request->date_of_birth,
                    'phone' => $request->phone,
                    'address' => $request->address,
                ]
            );

            // Create a medical history entry for the patient
            $medicalHistory = MedicalHistory::updateOrCreate([
                    'patient_id' => $request->patient_id
                ],[
                    'doctor_name' => $request->doctor_name,
                    'doctor_phone' => $request->doctor_phone,
                    'doctor_role' => $request->doctor_role,
                ]
            );


            $data['nurses_assessment'] = serialize($data['nurses_assessment']);
            $data['nursing_notes'] = serialize($data['nursing_notes']);
            $data['appointment_date'] = date('Y-m-d H:i:s');
            $appointment = Appointment::create($data);

            $data = $request->all();
            $data['dose_quantity'] = $request->amount_prescribed;            
            TreatmentsHistory::create($data);

            $response = [
                "status"=> true,
                "message"=> 'Success'
            ];
            return response()->json($response);           
        }
        catch(\Exception $e) {
            $response = [
                "status"=> false,               
                "message"=> $e->getMessage(),
                "data"=> []
            ];
            return response()->json($response);
        }        
    } 

    // Update the specified resource in storage.
    public function viewAddon(Request $request)
    {
        $vitamin = Formula::select('formulas.formula_name','formula_add_on.*')
            ->leftJoin('formula_add_on', 'formulas.id', '=', 'formula_add_on.formula_id')
            ->where('formula_add_on.formula_id', $request->add_on_id)
            ->where('formula_add_on.treatment_id', $request->treatment_id)
            ->first();  
        unset($vitamin->use_by_date); 
        unset($vitamin->created_at);     
        if($vitamin) {
/*            $vitamin->vitamin_name = $vitamin->formula_name;
            $vitamin->lot_number = (string)$vitamin->id;
            $vitamin->vitamin_id = $vitamin->formula_id;
            $vitamin->doses = $vitamin->dosage;
            $vitamin->left_total = number_format($vitamin->left_total,2);
            $vitamin->used_total = number_format($vitamin->used_total,2);
            $vitamin->qr_image = "";*/
            $vitamin->left_total = number_format($vitamin->left_total,2);
            $vitamin->used_total = number_format($vitamin->used_total,2);            
            $response = [
                "status"=> 1,
                "message"=> 'Success',
                "data"=> $vitamin
            ];
        }
        else {
            $response = [
                "status"=> 0,
                "message"=> 'Add-on not found!',
                "data"=> []
            ];
        }
        return response()->json($response);      
    }   

    public function scanAddon(Request $request)
    {       

        try {
            
            $FormulaAddOn = FormulaAddOn::where('formula_id', $request->add_on_id)->firstOrFail();

            if($FormulaAddOn->expiry_date != NULL && strtotime($FormulaAddOn->expiry_date) <= strtotime(date('Y-m-d'))) {
                $FormulaAddOn->status = "expired";
                $FormulaAddOn->save();
                $response = [
                    "status"=> false,               
                    "message"=> "The selected vitamin has expired.",
                    "data"=> []
                ];
                return response()->json($response);                 
            }
            
            if( $FormulaAddOn->dosage == $FormulaAddOn->used_total) {
                $FormulaAddOn->status = "expired";
                $FormulaAddOn->save();
            }            

            if($FormulaAddOn->status && $FormulaAddOn->status =='expired') {
                $response = [
                    "status"=> false,               
                    "message"=> "The selected vitamin has expired.",
                    "data"=> []
                ];
                return response()->json($response);                 
            }

            if($FormulaAddOn->left_total < $request->amount_prescribed) {
                $response = [
                    "status"=> false,               
                    "message"=> "Not enough volume ({$request->amount_prescribed}) left. Try using a smaller or equal volume ({$FormulaAddOn->left_total}).",
                    "data"=> []
                ];
                return response()->json($response);                
            }

            $reduce_volume = $FormulaAddOn->used_total + $request->amount_prescribed;
            $FormulaAddOn->left_total = $FormulaAddOn->dosage - $reduce_volume;
            $FormulaAddOn->used_total = $reduce_volume;

            if( $FormulaAddOn->dosage == $FormulaAddOn->used_total) {
                $FormulaAddOn->status = "expired";
            }

            if(!$FormulaAddOn->opened_date && !$FormulaAddOn->expiry_date) {
                $FormulaAddOn->opened_date = date('Y-m-d');
                $FormulaAddOn->expiry_date = date('Y-m-d',strtotime("+ {$FormulaAddOn->opened_days} days",strtotime(date('Y-m-d'))));
            }

            $FormulaAddOn->last_used_date = date('Y-m-d');
            $FormulaAddOn->opened_days = $FormulaAddOn->opened_days;
            $FormulaAddOn->last_used_by = $request->patient_id;
            $FormulaAddOn->treatment_id = $request->treatment_id;

            $FormulaAddOn->status = "opened";
            $FormulaAddOn->save();        

            $response = [
                "status"=> true,
                "message"=> 'Ready',
                "data"=> $FormulaAddOn
            ];
        }
        catch(\Throwable $e){
            $response = [
                "status"=> 0,
                "message"=> 'Error!',
                "data"=> []
            ];            
        }
        return response()->json($response);      
    }  

    // Remove the specified resource from storage.
    public function destroy(Request $request, $id)
    {
        $Vitamin = Vitamin::findOrFail($id);
        $Vitamin->delete();
        return response()->json(null, 204);
    }

    // Store a newly created resource in storage.
    public function selectFormula(Request $request)
    {
        try {
            $Formula = Formula::findOrFail($request->formula_id);
            return response()->json($Formula, 201);
        }
        catch(\Throwable $e){
            return response()->json(['message'=> "Your request has been accepted for processing."], 202);
        }
    }

    // Store a newly created resource in storage.
    public function newAppointment(Request $request)
    {
        $link = "";
        $mail = Mail::to($request->email)->send(new AppointmentMail($link));
        return response()->json(['message'=> "Request has been sent.",'link'=> $mail], 201);
    }        
}
