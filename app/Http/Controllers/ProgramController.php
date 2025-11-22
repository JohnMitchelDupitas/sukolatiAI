<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Program;

class ProgramController extends Controller
{
    /**
     * Display a listing of the resource.
     */

    public function index()
    {
        $program = Program::latest()->get();
        //$programs = Program::with('user')->latest()->get();

          if (!$program) { //dito din magiging programs
            return response()->json([
                'status' => 'error',
                'message' => 'No records found',
            ], 404);
        }
        return response()->json([
            'status' => 'success',
            'data' => $program,
            //tapos dito sa mga program e magiging programs
        ]);
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'code' => 'required|string|max:25',
            'description' => 'required|string|max:100',
        ]);

        // if ($request->user()) {
        //     $validated['user_id'] = $request->user()->id;
        // }

        // Create the program
        $program = Program::create($validated);

        return response()->json([
            'status' => 'success',
            'message' => 'Program created successfully',
            'data' => $program,
        ], 201);
    }

    /**
     * Display the specified resource.
     */
    public function show(int $id)
    {
        $program = Program::find($id);
        //$program = Program::with('user')->find($id);

        if (!$program) {
            return response()->json([
                'status' => 'error',
                'message' => 'Program not found',
            ], 404);
    }
        return response()->json([
            'status' => 'success',
            'data' => $program,
        ]);
    }
    /**
     * Show the form for editing the specified resource.
     */
    public function edit(string $id)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        $program = Program::find($id);
        if (!$program) {
            return response()->json([
                'status' => 'error',
                'message' => 'Program not found',
            ], 404);
        }

        $validated = $request->validate([
            'code' => 'required|string|max:25',
            'description' => 'required|string|max:100',
        ]);

        $program->update($validated);

        return response()->json([
            'status' => 'success',
            'message' => 'Program updated successfully',
            'data' => $program,
        ]);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        $program = Program::find($id);
        if (!$program) {
            return response()->json([
                'status' => 'error',
                'message' => 'Program not found',
            ], 404);
        }

        $program->delete();

        return response()->json([
            'status' => 'success',
            'message' => 'Program deleted successfully',
        ]);
    }
}
