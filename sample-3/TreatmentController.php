<?php

namespace App\Http\Controllers;
use App\Models\Treatment;
use App\Models\Vitamin;
use Illuminate\Http\Request;

class TreatmentController
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        $treatments = Treatment::with('vitamins')->orderBy('id', 'DESC')->get();
        return view('admin.treatment.index', compact('treatments'));
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create()
    {
        $vitamins = Vitamin::orderBy('vitamin_name', 'ASC')->get();
        return view('admin.treatment.create', compact('vitamins'));
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'treatment_timer_start' => 'required',
            'description' => 'nullable|string',
        ]);
        $treatment = Treatment::create($request->all());
        $i =  0;
        foreach ($request->vitamin_ids as $vitamin) {
           $treatment->vitamins()->attach($vitamin, [
                    'doses' => $request->vitamin_doses[$i], 
                    'left_total' => $request->vitamin_doses[$i], 
                    'created_at' => date('Y-m-d H:i:s'), 
                    'updated_at' => date('Y-m-d H:i:s')
                ]
            );
           $i++;
        }
        return redirect()->route('treatment.index')->with('success', 'Treatment created successfully.');
    }

    /**
     * Display the specified resource.
     *
     * @param  \App\Models\Treatment  $treatment
     * @return \Illuminate\Http\Response
     */
    public function show(Treatment $treatment)
    {
        // Load vitamins for the treatment
        $treatment->load('vitamins');

        // Extract vitamin IDs
        $vitaminIds = [];
        if($treatment->vitamins->isNotEmpty()) {
            $vitaminIds = $treatment->vitamins->pluck('id')->toArray();
        }      

        $vitamins = Vitamin::orderBy('vitamin_name', 'ASC')->get();

       return view('admin.treatment.show', compact('treatment', 'vitamins', 'vitaminIds'));
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  \App\Models\Treatment  $treatment
     * @return \Illuminate\Http\Response
     */
    public function edit(Treatment $treatment)
    {
        return view('admin.treatment.edit', compact('treatment'));
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \App\Models\Treatment  $treatment
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, Treatment $treatment)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'vitamin_ids' => 'required|array', // Ensure vitamins are provided
            'vitamin_doses' => 'required|array' // Ensure doses are provided
        ]);

        // Update treatment details
        $treatment->update($request->all());

        // Detach all previous vitamins
        $treatment->vitamins()->detach();

        // Attach new vitamins with doses
        foreach ($request->vitamin_ids as $index => $vitamin_id) {
            $treatment->vitamins()->attach($vitamin_id, [
                    'doses' => $request->vitamin_doses[$index], 
                    'left_total' => $request->vitamin_doses[$index], 
                    'updated_at' => date('Y-m-d H:i:s')
                ]
            );            
        }

        return redirect()->route('treatment.index')->with('success', 'Treatment updated successfully.');
    }


    /**
     * Remove the specified resource from storage.
     *
     * @param  \App\Models\Treatment  $treatment
     * @return \Illuminate\Http\Response
     */
    public function destroy(Treatment $treatment)
    {
        $treatment->delete();

        return redirect()->route('treatment.index')->with('success', 'Treatment deleted successfully.');
    }

    public function destroyBulk(Request $request, $ids)
    {
        // Convert the comma-separated string of IDs to an array
        $userIds = explode(',', $request->ids);
        // Delete users with the specified IDs
        Treatment::whereIn('id', $userIds)->delete();        
        // Redirect back with a success message
        return redirect()->back()->with('success', 'Deleted successfully');
    }     
}
