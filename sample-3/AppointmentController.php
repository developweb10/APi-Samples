<?php

namespace App\Http\Controllers;

use App\Models\Appointment;
use App\Models\Patient;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class AppointmentController
{
    public function index()
    {
        $appointments = Appointment::all();
        return response()->json($appointments);
    }

    public function store(Request $request)
    {
        $data = $request->all();
        $data['nurses_assessment'] = serialize($data['nurses_assessment']);
        $data['appointment_date'] = date('Y-m-d H:i:s');

        $appointment = Appointment::create($data);
        
        return response()->json($appointment, 201);
    }

    public function getAppointmentsByPatient(Request $request)
    {
        // Retrieve all appointments for the given patient
        if($request->patient_id) {
            $patientId = $request->patient_id;
        }

        $patient = Patient::select()->where('id', $patientId)->first();
        $doctors = Patient::leftJoin('appointments', 'patients.id', '=', 'appointments.patient_id')
            ->where('patients.id', $patientId)
            ->whereNotNull('appointments.doctor_name')
            ->select('appointments.doctor_name', DB::raw('count(*) as appointments_count'))
            ->groupBy('appointments.doctor_name')
            ->get();  
        $patient->doctors = $doctors;  

        $appointments = Appointment::where('patient_id', $patientId)->orderBy('id', 'desc')->get();
        $unsetKeys = ['doctor_id','doctor_name','blood_pressure','heart_rate','enter_02','allergies','nurses_assessment', 'add_ons', 'infused_over', 'nursing_notes', 'treatment_id', 'vitamin_id', 'amount_prescribed', 'member_name', 'created_at', 'updated_at','memeber_name'];
        if ($appointments) {
            foreach ($appointments as $index => $appointment) {
                foreach ($unsetKeys as $key) {
                    if (isset($appointment[$key])) {}
                    unset($appointments[$index][$key]);                    
                }
            }
        } 

        // Check if appointments exist for the patient
        if ($appointments->isEmpty()) {
            return response()->json([
                'status'=> true,
                'message' => 'No appointments were found for this patient. Please check the patient\'s details or schedule a new appointment.',
                'patient'=> $patient,
                'appointments'=> []
            ], 404);
        }

        // Return the appointments as a JSON response
        return response()->json([
            'status'=>true, 
            'message'=> 'Success',
            'patient'=> $patient, 
            'appointments'=> $appointments
        ], 200);
    }    

/// Patient Medical History
    public function medicalHistory(Request $request)
    {
        // Retrieve all appointments for the given patient
        if($request->patient_id) {
            $patientId = $request->patient_id;
        }

        $patient = Patient::where('id', $patientId)
            ->with(['medicalHistories' => function ($query) {
                $query->latest()->limit(1);
            }])
            ->first();
        $latestMedicalHistory = $patient->medicalHistories->first();
        if ($latestMedicalHistory) {
            unset($latestMedicalHistory->notes);
        }
        // Return the appointments as a JSON response
        return response()->json([
            'status'=>true, 
            'message'=> 'Success',
            'patient'=> $patient, 
        ], 200);
    } 

    public function show(Request $request)
    {
        $appointment = Appointment::where('id',$request->appointment_id)->first();
        if($appointment) {
            $appointment->nurses_assessment = unserialize($appointment->nurses_assessment); 
            $appointment->nursing_notes = unserialize($appointment->nursing_notes); 
        }        
        return response()->json([
            'status'=> 1, 
            'message'=> 'Success',
            'appointmen'=> $appointment
        ], 200);
    }

    public function update(Request $request, $id)
    {
        $request->validate([
            'user_id' => 'sometimes|required|exists:users,id',
            'service_id' => 'sometimes|required|exists:services,id',
            'appointment_date' => 'sometimes|required|date',
            'notes' => 'nullable|string',
        ]);

        $appointment = Appointment::findOrFail($id);
        $appointment->update($request->all());
        return response()->json($appointment);
    }

/*    public function destroy($id)
    {
        $appointment = Appointment::findOrFail($id);
        $appointment->delete();
        return response()->json(null, 204);
    }*/
}
