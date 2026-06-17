<?php

namespace Database\Seeders;

use App\Models\ColumnStatusOption;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // Default super admin
        User::firstOrCreate(['email' => 'admin@im-master.local'], [
            'name'       => 'System Administrator',
            'password'   => Hash::make('password'),
            'role'       => 'super_admin',
            'department' => 'IT',
            'is_active'  => true,
        ]);

        // Seed default status options
        $options = [
            ['sap_tracking', 'sap_status', [
                ['SAP PM Completed', '#1D9E75'],
                ['In Progress',      '#BA7517'],
                ['Pending',          '#888780'],
                ['On Hold',          '#E24B4A'],
                ['Not Started',      '#B4B2A9'],
            ]],
            ['sap_tracking', 'afc_edw_status', [
                ['Approved',     '#1D9E75'],
                ['In Review',    '#BA7517'],
                ['Pending',      '#888780'],
                ['Not Submitted','#B4B2A9'],
            ]],
            ['dc_tracking', 'asb_document_status', [
                ['UNDER REVIEW', '#BA7517'],
                ['Submitted',    '#378ADD'],
                ['RLMU',         '#1D9E75'],
                ['Approved',     '#1D9E75'],
                ['Pending',      '#888780'],
                ['Rejected',     '#E24B4A'],
            ]],
            ['dc_tracking', 'afc_upload_status', [
                ['Uploaded',     '#1D9E75'],
                ['Pending',      '#888780'],
                ['Not Required', '#B4B2A9'],
            ]],
            ['dc_tracking', 'rlmu_upload_status', [
                ['Uploaded',     '#1D9E75'],
                ['Pending',      '#888780'],
                ['Not Required', '#B4B2A9'],
            ]],
            ['project_wells', 'e_pad_closeout', [
                ['Closed',   '#1D9E75'],
                ['Open',     '#E24B4A'],
                ['Pending',  '#888780'],
            ]],
            ['project_wells', 'rlmu_status', [
                ['Ready',     '#1D9E75'],
                ['Not Ready', '#E24B4A'],
            ]],
        ];

        foreach ($options as [$table, $column, $values]) {
            foreach ($values as $i => [$value, $color]) {
                ColumnStatusOption::firstOrCreate(
                    ['source_table' => $table, 'source_column' => $column, 'option_value' => $value],
                    ['option_label' => $value, 'option_color' => $color, 'sort_order' => $i, 'is_active' => true]
                );
            }
        }
    }
}
