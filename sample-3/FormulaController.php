<?php

namespace App\Http\Controllers;

use App\Models\Formula;
use Illuminate\Support\Facades\Session;
use Illuminate\Http\Request;

class FormulaController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $formulas = Formula::orderBy('id', 'DESC')->get();
        return view('formulas.index', compact('formulas'));
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        return view('formulas.create');
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $request->validate([
            'formula_name' => 'required|unique:formulas,formula_name',
            'vial_size' => 'required|numeric',
            'dosage' => 'required|numeric',
            'open_days' => 'required|numeric',
        ]);

        // Set default value for 'item_type' if not provided
        $itemType = $request->input('item_type', 'vitamin');

        // Prepare data for creation
        $data = $request->only(['formula_name', 'vial_size', 'dosage', 'open_days']);
        $data['item_type'] = $itemType;

        // Create a new Formula record
        $formula = Formula::create($data);

        $vitaminData = [
            'status' => $request->status,  // From your previous logic
            'left_total' => $request->vial_size,
            'dosage' => $request->vial_size,
            'opened_days' => $request->open_days,
        ];

        $formula->addOn()->create($vitaminData);

        return redirect()->route('formulas.index')->with('success', 'Formula created successfully.');
    }

    /**
     * Display the specified resource.
     */
    public function show($id)
    {
        $formula = Formula::findOrFail($id);
        return view('formulas.show', compact('formula'));
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Formula $formula)
    {
        return view('formulas.edit', compact('formula'));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, $id)
    {
        // Validate the incoming request
        $request->validate([
            'formula_name' => 'required|string|max:255',
            'vial_size' => 'required|numeric',
            'dosage' => 'required|numeric',
            'open_days' => 'required|numeric',
        ]);

        // Find the Formula by ID or fail if not found
        $formula = Formula::findOrFail($id);

        // Update only if no duplicate name exists
        if (Formula::where('formula_name', $request->formula_name)
            ->where('id', '!=', $id)
            ->exists()) {
            return redirect()->back()->with('error', 'Formula name already exists.');
        }        

        // Prepare data for update
        $data = $request->only(['formula_name', 'vial_size', 'dosage', 'open_days']);
        
        // Set default value for 'item_type' if not provided
        $itemType = $request->input('item_type', 'vitamin');
        $data['item_type'] = $itemType;

        // Update the Formula record
        $formula->update($data);


        $vitaminData = [
            'status' => $request->status,  // From your previous logic
            'left_total' => $request->vial_size,
            'dosage' => $request->vial_size,
            'opened_days' => $request->open_days,
        ];

        // Check if the formula already has an associated add-on
        if ($formula->addOn) {
            // Update the existing add-on
            $formula->addOn->update($vitaminData);
        } else {
            // Create a new add-on associated with the formula
            $formula->addOn()->create($vitaminData);
        }


        // Redirect with success message
        return redirect()->route('formulas.index')->with('success', 'Formula updated successfully.');
    }


    public function destroy(Request $request, $id)
    {
        $vitamin = Formula::findOrFail($id);  
        $vitamin->save();        
        if ($vitamin) {
            $vitamin->delete();
            Session::flash('success', 'Deleted successfully.');
        } 
        else {
            Session::flash('success', 'Error!.');
        }        
        //return redirect()->route('formulas');   
        return redirect()->back()->with('success', 'Deleted successfully');   
    } 

    public function destroyBulk(Request $request, $ids)
    {
        // Convert the comma-separated string of IDs to an array
        $userIds = explode(',', $request->ids);
        // Delete users with the specified IDs
        Formula::whereIn('id', $userIds)->delete();        
        // Redirect back with a success message
        return redirect()->back()->with('success', 'Deleted successfully');
    } 
}
