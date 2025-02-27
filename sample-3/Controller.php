<?php

namespace App\Http\Controllers;

use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Foundation\Validation\ValidatesRequests;
use Illuminate\Routing\Controller as BaseController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use App\Models\Vitamin;
use App\Models\Formula;
use Illuminate\Support\Facades\Session;
use SimpleSoftwareIO\QrCode\Facades\QrCode;
use Illuminate\Support\Facades\Storage;


class Controller extends BaseController
{
    use AuthorizesRequests, ValidatesRequests;

    public function index()
    {
        $vitamins = Vitamin::orderBy('id', 'DESC')->get();
        if($vitamins) {
            foreach ($vitamins as $item) {
                $item->expiration_date = date('F j, Y', strtotime($item->expiry_date));                
            }
        }
        return view('admin/view-vitamins',compact('vitamins'));
    }

    public function create()
    {  
        $Formulas = Formula::Where('item_type','vitamin')->orderBy('formula_name', 'ASC')->get();          
        return view('admin/add-vitamins', compact('Formulas'));
    }

    public function save(Request $request)
    {
        // Validate the incoming request data
        $validator = Validator::make($request->all(), [
            'vitamin_name' => 'required',
            'lot_number' => 'required|unique:vitamins,lot_number',
            'quantity' => 'required|numeric',
            'dose_quantity' => 'required|numeric',
            'opened_days' => 'required|integer|gt:0',
        ], [
            'vitamin_name.required' => 'The vitamin name is required.',
            'lot_number.required' => 'The lot number is required.',
            'quantity.required' => 'The quantity is required.',
            'dose_quantity.required' => 'The dose quantity is required.',
            //'num_of_doses.required' => 'The number of doses is required.',
            // 'expiry_date.required' => 'The expiry date is required.',
            'opened_days.required' => 'The opened days field is required.',
            'opened_days.integer' => 'The opened days must be an integer.',
            'opened_days.gt' => 'The opened days must be greater than 0.',
        ]);

/*        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }*/
        // Proceed with further processing if validation passes

        if ($validator->fails()) {
            return redirect()->back()
                ->withErrors($validator)
                ->withInput();
        }
        try {
            $numberOfDoses = $request->quantity / $request->dose_quantity;
            $expiry_date = date('Y-m-d',strtotime("+ {$request->opened_days} days",strtotime(date('Y-m-d'))));
            $vitamin =  Vitamin::create([
                    'vitamin_name'=> $request->vitamin_name,
                    'lot_number'=> $request->lot_number,
                    'quantity'=> $request->quantity,
                    'dose_quantity'=> $request->dose_quantity,
                    'num_of_doses'=> $numberOfDoses,  
                    'left_total'=> $numberOfDoses,                  
                    'used_total' => ($request->used_total) ? $request->used_total : 0,
                    'expiry_date'=> ($request->expiry_date) ? $request->expiry_date : $expiry_date,
                    'opened_days'=> $request->opened_days,                    
                ]);
            $itemId = $vitamin->id;
            $qr_image = "vitamin-{$itemId}-qr-image.svg";
            $vitamin->qr_image = $qr_image;
            QrCode::size(500)->generate($itemId, storage_path('app/public/qr-codes/'.$qr_image));            
        }
        catch(\Exception $e){
            echo $e->getMessage();
        }
        $vitamin->save();  
        Session::flash('success', 'Created successfully.');
        return redirect()->route('vitamins.show', $itemId);
    }  

    public function show($id)
    {
        $vitamin = Vitamin::findOrFail($id);     
        return view('admin/edit-vitamins', ['vitamin' => $vitamin]);
    } 

    public function update(Request $request, $id)
    {
        $validator = Validator::make($request->all(), [
            'vitamin_name' => 'required',
            'lot_number' => 'required',
            'quantity' => 'required|numeric',
            'dose_quantity' => 'required|numeric',
            'opened_days' => 'required|integer|gt:0',       
        ], [
            'vitamin_name.required' => 'The Formula Name field is required.',
            'lot_number.required' => 'The Lot# field is required.',
            'quantity.required' => 'The Vial size field is required.',
            'dose_quantity.required' => 'The Dosage field is required.',
            'num_of_doses.required' => 'The Total Dose field is required.',
            'used_total.required' => 'The Doses Used field is required.',
            'left_total.required' => 'The Remaining Doses field is required.',
            'opened_date.required' => 'The First opened date field is required.',
            'last_used_date.required' => 'The Last used date field is required.',
            'expiry_date.required' => 'The Vial Expiration field is required.',
            'opened_days.required' => 'The Open Days field is required.',
            'use_by_date.integer' => 'The Use By Date field is required.',
            'last_used_by' => 'The Last Used By field is required.',
        ]);

        if ($validator->fails()) {
            return redirect()->back()
                ->withErrors($validator)
                ->withInput();
        } 
        $numberOfDoses = $request->quantity / $request->dose_quantity;

        // Update only if no duplicate name exists
        /*
        if (Vitamin::where('vitamin_name', $request->vitamin_name)
            ->where('id', '!=', $id)
            ->exists()) {
            return redirect()->back()->with('error', 'Formula name already exists.');
        } 
        */

        // Update only if no duplicate name exists
        if (Vitamin::where('lot_number', $request->lot_number)
            ->where('id', '!=', $id)
            ->exists()) {
            return redirect()->back()->with('error', 'Lot Number is already in use.');
        } 

        $vitamin = Vitamin::findOrFail($id);  
        $vitamin->vitamin_name = $request->vitamin_name; 
        $vitamin->lot_number = $request->lot_number;
        $vitamin->quantity = $request->quantity; 
        $vitamin->dose_quantity = $request->dose_quantity;         
        $vitamin->num_of_doses = $numberOfDoses;        
        $vitamin->used_total = $request->used_total; 
        $vitamin->left_total = $request->left_total;         
        $vitamin->opened_date = $request->opened_date;
        $vitamin->last_used_date = $request->last_used_date;
        $vitamin->expiry_date = $request->expiry_date;
        $vitamin->opened_days = $request->opened_days; 
        $vitamin->use_by_date = $request->use_by_date;
        $vitamin->last_used_by = $request->last_used_by;

        try{
            if(!$vitamin->qr_image) {
                $qr_image = "vitamin-{$id}-qr-image.svg";
                $vitamin->qr_image = $qr_image;
                QrCode::size(500)->generate($id, storage_path('app/public/qr-codes/'.$qr_image)); 
            }
        }
        catch(\Exception $e){
        }
        $vitamin->save(); 
        Session::flash('success', 'Updated successfully.');
        return redirect()->route('vitamins.show', $id);
        // return redirect()->route('vitamins');                   
    }

    public function destroy(Request $request, $id)
    {
        $vitamin = Vitamin::findOrFail($id);  
        $vitamin->save();        
        if ($vitamin) {
            $vitamin->delete();
            Session::flash('success', 'Deleted successfully.');
        } else {
            Session::flash('success', 'Vitamin not successfully.');
        }        
        return redirect()->route('vitamins');      
    } 

    public function destroyBulk(Request $request, $ids)
    {

        // Convert the comma-separated string of IDs to an array
        $userIds = explode(',', $request->ids);
        // Delete users with the specified IDs
        Vitamin::whereIn('id', $userIds)->delete();        
        // Redirect back with a success message
        return redirect()->back()->with('success', 'Users deleted successfully');
    }       
}
