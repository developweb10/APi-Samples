<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\MRN;
use Illuminate\Support\Facades\Session;

class MRNController
{
    public function index()
    {
        $mrns = MRN::leftJoin('vitamins', 'mrns.vitamin_id', '=', 'vitamins.id')
              ->select('mrns.*', 'vitamins.vitamin_name', 'vitamins.qr_image') 
              ->orderBy('mrns.id', 'DESC')
              ->paginate(10);
        return view('admin.mrns.index', compact('mrns'));
    }

    public function create()
    {
        return view('mrns.create');
    }

    public function store(Request $request)
    {
        $request->validate([
            'mrn' => 'required|unique:mrns,mrn',
            'patient_name' => 'required|string|max:255',
            'dob' => 'required|date',
        ]);

        MRN::create($request->all());

        return redirect()->route('mrns.index')->with('success', 'MRN created successfully.');
    }

    public function show($id)
    {
        $mrn = MRN::leftJoin('vitamins', 'mrns.vitamin_id', '=', 'vitamins.id')
              ->select('mrns.*', 'vitamins.vitamin_name', 'vitamins.qr_image')
              ->where('mrns.id', $id)
              ->first();
     
        return view('admin.mrns.show', compact('mrn'));
    }

    public function edit(MRN $mrn)
    {
        return view('mrns.edit', compact('mrn'));
    }

    public function update(Request $request, MRN $mrn)
    {
        $request->validate([
            'mrn' => 'required|unique:mrns,mrn,' . $mrn->id,
            'patient_name' => 'required|string|max:255',
            'dob' => 'required|date',
        ]);

        $mrn->update($request->all());

        return redirect()->route('mrns.index')->with('success', 'MRN updated successfully.');
    }

    public function destroy(Request $request, $id)
    {
        $vitamin = MRN::findOrFail($id);  
        $vitamin->save();        
        if ($vitamin) {
            $vitamin->delete();
            Session::flash('success', 'Deleted successfully.');
        } else {
            Session::flash('success', 'Vitamin not successfully.');
        }        
        return redirect()->route('mrns');      
    } 

    public function destroyBulk(Request $request, $ids)
    {

        // Convert the comma-separated string of IDs to an array
        $userIds = explode(',', $request->ids);
        // Delete users with the specified IDs
        MRN::whereIn('id', $userIds)->delete();        
        // Redirect back with a success message
        return redirect()->back()->with('success', 'Deleted successfully');
    }    
}
