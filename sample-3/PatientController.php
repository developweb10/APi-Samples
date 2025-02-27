<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Mail;
use App\Models\Patient;
use App\Mail\PatientRegistrationMail;
use SimpleSoftwareIO\QrCode\Facades\QrCode;
use Illuminate\Support\Facades\Storage;

class PatientController
{


    public function search(Request $request)
    {
        $patients = [];
        if ($request->has('user_id')) {
            $patients = Patient::where('id', $request->user_id)->get();
        }
        elseif ($request->has('last_name') || $request->has('date_of_birth')) {
            $query = Patient::query();

            if ($request->has('last_name')) {
                $query->where('last_name', 'like', '%' . $request->last_name . '%');
            }

            if ($request->has('date_of_birth')) {
                $query->orWhere('date_of_birth', date('Y-m-d', strtotime(str_replace('-', '/', $request->date_of_birth))));
            }
            
            $patients = $query->get();
        } 

        // Convert the profile_photo path to a full URL for each patient
        $patients->transform(function ($patient) {
            $patient->date_of_birth = $patient->date_of_birth ? $patient->date_of_birth : "";
            $patient->address = $patient->address ?$patient->address : "";
            $patient->profile_photo = $patient->profile_photo ? url(Storage::url($patient->profile_photo)) : "";
            return $patient;
        });

        if(count($patients) > 0) {    
            $response = [
                "status"=> 1,
                'message' => 'Success.',
                'patient'=> $patients
            ];
        }
        else {
            $response = [
                "status"=> 0,               
                "message"=> 'No patients were found.',
                "data"=> [],
            ];
        }
        return response()->json($response);
    }

    public function store(Request $request)
    {
        // Validate the incoming request data
        $validator = Validator::make($request->all(), [
            'email' => 'required|string|email|max:255|unique:patients,email',
            'phone' => 'required|string|max:20',
        ]);

        // Check if validation fails
        if ($validator->fails()) {
            return response()->json([
                'status'=> 0,
                'message' => 'The email has already been taken.',
                // 'messages' => $validator->errors(),
            ], 422);
        }

        $email = $request->input('email');
        $namePart = explode('@', $email)[0];
        $name = str_replace(['.', '_', '-'], ' ', $namePart);
        $name = ucwords($name);

        // Generate the next patient number
        $lastPatient = Patient::orderBy('id', 'desc')->first();
        if ($lastPatient) {
            $nextNumber = $lastPatient->last_number + 1;
        } else {
            $nextNumber = 1; 
        }
        $patientNumber = 'WWP'.$nextNumber;

        // Create or update patient record
        $patient = Patient::create(
            [
                'first_name'=> ($request->last_name) ? $request->first_name : $name,
                'last_name'=> ($request->first_name) ? $request->last_name : $name,
                'email' => $request->input('email'),
                'patient_number' => $patientNumber,
                'phone' => $request->input('phone'),
                'last_number' => $nextNumber,
            ]
        );


        $patient_qrcode = "patient-{$patient->id}-qr-image.svg";
        $patient->patient_qrcode = $patient_qrcode;
        // QrCode::size(500)->generate($patient->id, storage_path('app/public/users/'.$patient_qrcode));  
        $patient->save();

        // Send the email with the appointment form link
        $formLink = 'https://example.com/patient/register?token=abc123';
        Mail::to($request->input('email'))->send(new PatientRegistrationMail($formLink));
        return response()->json(['status'=> 1, 'message' => 'Appointment link has been sent successfully.','patient_qrcode'=> $patient_qrcode]);
    }

    public function savePhoto(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'patient_id' => 'required',
            'profile_photo' => 'required|image|mimes:jpeg,png,jpg,gif',
        ]);

        // Check if validation fails
        if ($validator->fails()) {
            return response()->json([
                'status' => 0,
                'messages' => $validator->errors(),
            ], 422);
        }

        // Store the uploaded file
        if ($request->hasFile('profile_photo')) {
            $file = $request->file('profile_photo');
            $filename = time() . '_' . $file->getClientOriginalName();
            $path = $file->storeAs('profile_photos', $filename, 'public'); // Stores in storage/app/public/profile_photos
            
            // Create a medical history entry for the patient
            $medicalHistory = Patient::updateOrCreate( [
                    'id' => $request->patient_id
                ],[
                    'profile_photo' => $path,
                ]
            );

            $response = [
                "status" => true,
                "message" => 'Uploaded successfully.',
                "file_path" => Storage::url($path), // Returns the URL to the stored file
            ];
        } else {
            $response = [
                "status" => false,
                "message" => 'No file uploaded.',
            ];
        }
        
        return response()->json($response);        
    }

    public function update(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'user_id' => 'required',
            'first_name' => 'required|string',
            'last_name' => 'required|string',
            'date_of_birth' => 'required|string',
            'email' => 'required|string|email|max:255',
            'phone' => 'required|string|max:20',
            'address' => 'required|string',
        ]);

        // Check if validation fails
        if ($validator->fails()) {
            return response()->json([
                'error' => 'Validation Error',
                'messages' => $validator->errors(),
            ], 422);
        }
        try {
            $patient = Patient::find($request->user_id);
            $patient->first_name = $request->first_name;
            $patient->last_name = $request->last_name;
            $patient->date_of_birth = $request->date_of_birth;
            $patient->email = $request->email;
            $patient->phone = $request->phone;
            $patient->address = $request->address;        
            $patient->save();
            $response = [
                "status"=> true,               
                "message"=> 'Updated successfully.',
                "data"=> $patient
            ];            
        }
        catch(\Throwable $e){
            $response = [
                "status"=> false,               
                "message"=> $e->getMessage(),
                "data"=> []
            ];
        }
        return response()->json($response);
    }    
}
