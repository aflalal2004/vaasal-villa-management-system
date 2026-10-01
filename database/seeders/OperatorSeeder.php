<?php

namespace Database\Seeders;

use App\Models\ContractRate;
use App\Models\OperatorContract;
use App\Models\Property;
use App\Models\TourOperator;
use App\Models\User;
use App\Models\VillaType;
use App\Modules\Operators\Services\OperatorService;
use Illuminate\Database\Seeder;

class OperatorSeeder extends Seeder
{
    public function run(): void
    {
        $p = Property::first();
        $svc = app(OperatorService::class);

        $sunrise = TourOperator::create([
            'property_id' => $p->id, 'code' => 'TO-26-0001', 'company_name' => 'Sunrise Tours Lanka', 'legal_name' => 'Sunrise Tours Lanka (Pvt) Ltd',
            'registration_no' => 'PV 104422', 'tax_id' => 'VAT-114422771', 'contact_name' => 'Dinesh Gunawardena', 'email' => 'reservations@sunrise-tours.test',
            'phone' => '+94 11 250 8899', 'address' => '45 Galle Road, Colombo 03', 'country' => 'Sri Lanka', 'website' => 'https://sunrise-tours.test',
            'status' => 'active', 'credit_limit' => 1500000, 'payment_terms_days' => 30, 'approved_at' => now()->subMonths(6),
            'bank_details' => "Commercial Bank · Colombo 03\nAcc 1000 2233 4455",
        ]);
        $c = OperatorContract::create(['tour_operator_id' => $sunrise->id, 'name' => 'Contract '.now()->format('Y').'/'.now()->addYear()->format('y'),
            'valid_from' => now()->startOfYear(), 'valid_to' => now()->addYear()->endOfYear(), 'commission_pct' => 10, 'discount_pct' => 0, 'deposit_pct' => 25,
            'release_days' => 14, 'rooming_cutoff_days' => 3, 'notes' => 'Net rates, 10% override commission on room revenue.']);
        foreach (VillaType::all() as $t) {
            ContractRate::create(['operator_contract_id' => $c->id, 'villa_type_id' => $t->id, 'net_rate' => round($t->base_rate * 0.82 / 500) * 500]);
        }
        $svc->createLogin($sunrise, 'Dinesh Gunawardena', 'operator@sunrise-tours.test', CoreSeeder::PASSWORD);

        $euro = TourOperator::create([
            'property_id' => $p->id, 'code' => 'TO-26-0002', 'company_name' => 'EuroAsia Journeys GmbH', 'legal_name' => 'EuroAsia Journeys GmbH', 'registration_no' => 'HRB 88213',
            'tax_id' => 'DE 288 441 210', 'contact_name' => 'Katrin Hoffmann', 'email' => 'groups@euroasia.test', 'phone' => '+49 89 1234 5670', 'address' => 'Leopoldstraße 20, München',
            'country' => 'Germany', 'status' => 'active', 'credit_limit' => 2500000, 'payment_terms_days' => 45, 'approved_at' => now()->subYear(),
        ]);
        $c2 = OperatorContract::create(['tour_operator_id' => $euro->id, 'name' => 'Winter & Summer '.now()->format('Y'), 'valid_from' => now()->startOfYear(),
            'valid_to' => now()->addYear()->endOfYear(), 'commission_pct' => 12, 'discount_pct' => 15, 'deposit_pct' => 30, 'release_days' => 21, 'rooming_cutoff_days' => 5]);
        $svc->createLogin($euro, 'Katrin Hoffmann', 'katrin@euroasia.test', CoreSeeder::PASSWORD);

        $pending = TourOperator::create([
            'property_id' => $p->id, 'code' => 'TO-26-0003', 'company_name' => 'Island Hopper Travels', 'contact_name' => 'Fathima Rizvi', 'email' => 'hello@islandhopper.test',
            'phone' => '+94 77 555 0101', 'country' => 'Sri Lanka', 'status' => 'pending',
        ]);
        \App\Models\DocumentSequence::create(['type' => 'operator', 'prefix' => 'TO', 'year' => (int) now()->format('Y'), 'next_number' => 4]);
    }
}
