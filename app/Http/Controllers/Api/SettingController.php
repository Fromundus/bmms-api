<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use Illuminate\Http\Request;

class SettingController extends Controller
{
    public function index()
    {
        $settings = Setting::first();
        return response()->json($settings);
    }

    public function save(Request $request)
    {
        $validated = $request->validate([
            'wfa_underweight' => 'required|integer',
            'wfa_normal' => 'required|integer',
            'wfa_overweight' => 'required|integer',
            'hfa_stunted' => 'required|integer',
            'hfa_normal' => 'required|integer',
            'hfa_tall' => 'required|integer',
            'wfs_wasted' => 'required|integer',
            'wfs_normal' => 'required|integer',
            'wfs_obese' => 'required|integer',
        ]);

        // If a record exists, update it. Otherwise, create one.
        $settings = Setting::first();

        if ($settings) {
            $settings->update($validated);
        } else {
            $settings = Setting::create($validated);
        }

        return response()->json($settings);
    }

}
