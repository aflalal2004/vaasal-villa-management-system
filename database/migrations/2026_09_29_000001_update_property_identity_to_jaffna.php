<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Property identity: Vaasal Villa, Jaffna, Sri Lanka · 0764413420 · aflalal2004@gmail.com.
 * Data-only. A value is replaced only while it still holds the earlier seeded default (or names the
 * wrong city), so anything an administrator has already customised is left alone.
 */
return new class extends Migration
{
    private const OLD_SETTINGS = [
        'site_tagline' => ['Private pool villas on the southern coast', 'Private pool villas in Jaffna, Sri Lanka'],
        'site_hero_title' => ['Slow mornings. Private pools. The ocean at the end of the garden.', 'Slow mornings. Private pools. The warm northern light of Jaffna.'],
        'site_hero_text' => ['Eight private villas set in a coconut grove above the Indian Ocean, with a restaurant, spa and a team that knows your name by the second day.',
            'Private villas set among palmyra palms in Jaffna, with a restaurant, spa and a team that knows your name by the second day.'],
        'contact_email' => ['reservations@vaasalvilla.test', 'aflalal2004@gmail.com'],
        'contact_phone' => ['+94 77 000 0000', '0764413420'],
        'contact_address' => ['12 Lighthouse Road, Galle, Sri Lanka', 'Jaffna, Sri Lanka'],
        'whatsapp_number' => ['94770000000', '94764413420'],
    ];

    /** [table, column, from, to] exact-substring replacements in seeded content. */
    private const TEXT = [
        ['charge_items', 'name', 'Tuk-tuk to Galle Fort (return)', 'Tuk-tuk to Jaffna Fort (return)'],
        ['charge_items', 'name', 'Excursion — whale watching (per person)', 'Excursion — Delft Island day trip (per person)'],
        ['outlets', 'receipt_header', "12 Lighthouse Road, Galle", "Jaffna, Sri Lanka\nTel 0764413420"],
        ['suppliers', 'name', 'Galle Fish Market Co-op', 'Jaffna Fish Market Co-op'],
        ['suppliers', 'name', 'Southern Fresh Produce', 'Northern Fresh Produce'],
        ['site_services', 'summary', 'Sri Lankan and coastal cooking from the morning market,', 'Jaffna Tamil and Sri Lankan cooking from the morning market,'],
        ['site_services', 'summary', 'Whale watching from Mirissa, Galle Fort walks, tea country and cooking classes.', 'Delft Island day trips, Jaffna Fort and Nallur Kandaswamy Kovil walks, Casuarina Beach and cooking classes.'],
        ['site_services', 'description', 'Colombo (BIA) transfers take about 2.5 hours.', 'Jaffna International Airport (Palaly) is about 30 minutes away; Colombo (BIA) transfers take 6–7 hours.'],
        ['testimonials', 'content', 'Reception sorted a whale-watching trip', 'Reception sorted a Delft Island day trip'],
        ['gallery_items', 'title', 'Morning on the coast', 'Morning in Jaffna'],
        ['bookings', 'group_name', 'EuroAsia Southern Coast Tour', 'EuroAsia Northern Heritage Tour'],
    ];

    public function up(): void
    {
        $p = DB::table('properties')->first();
        if ($p) {
            $upd = ['city' => 'Jaffna', 'country' => 'Sri Lanka', 'latitude' => 9.6615, 'longitude' => 80.0255, 'updated_at' => now()];
            if (in_array($p->phone, [null, '', '+94 77 000 0000'], true)) $upd['phone'] = '0764413420';
            if (in_array($p->email, [null, '', 'reservations@vaasalvilla.test'], true)) $upd['email'] = 'aflalal2004@gmail.com';
            if (in_array($p->address, [null, '', '12 Lighthouse Road'], true)) $upd['address'] = 'Jaffna';
            DB::table('properties')->where('id', $p->id)->update($upd);
        }

        foreach (self::OLD_SETTINGS as $key => [$old, $new]) {
            $cur = DB::table('settings')->where('key', $key)->value('value');
            if ($cur === null || $cur === '' || $cur === $old) {
                DB::table('settings')->updateOrInsert(['key' => $key], ['value' => $new, 'updated_at' => now(), 'created_at' => now()]);
            }
        }

        foreach (self::TEXT as [$table, $col, $from, $to]) {
            if (! DB::getSchemaBuilder()->hasColumn($table, $col)) continue;
            DB::table($table)->where($col, 'like', '%'.$from.'%')->get(['id', $col])
                ->each(fn ($row) => DB::table($table)->where('id', $row->id)->update([$col => str_replace($from, $to, $row->{$col})]));
        }
        // settings has a string primary key
        DB::table('settings')->where('value', 'like', '%Galle and Mirissa%')->get()->each(fn ($s) => DB::table('settings')->where('key', $s->key)
            ->update(['value' => str_replace('our drivers know every back road between Galle and Mirissa.', 'our drivers know every back road between Jaffna Fort, Nallur and Point Pedro.', $s->value)]));

        DB::table('charge_items')->where('code', 'EXC-WHALE')->update(['code' => 'EXC-DELFT']);

        Cache::forget('vv.settings');
    }

    public function down(): void
    {
        // Identity data is not reverted automatically.
    }
};
