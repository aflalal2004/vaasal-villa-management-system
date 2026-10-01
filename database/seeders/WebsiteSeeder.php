<?php

namespace Database\Seeders;

use App\Models\ChargeItem;
use App\Models\GalleryItem;
use App\Models\SiteService;
use App\Models\Testimonial;
use Illuminate\Database\Seeder;

class WebsiteSeeder extends Seeder
{
    public function run(): void
    {
        $img = fn ($id, $w = 1400) => VillaSeeder::img($id, $w);
        $services = [
            ['Vaasal Kitchen', 'utensils', 'photo-1414235077428-338989a2e8c0', null, 'Jaffna Tamil and Sri Lankan cooking from the morning market, served on the terrace or in your villa.',
                "Breakfast is served until 11am, lunch and dinner à la carte. Ask for the rice & curry — seven curries, made fresh every day. Everything can be charged to your villa."],
            ['Spa & wellness', 'leaf', 'photo-1544161515-4ab6ce6db874', 'SPA-60', 'Ayurvedic massage and signature rituals with local oils, in the spa pavilion or on your terrace.',
                "Treatments from 60 minutes. Book at reception or on WhatsApp; charges post to your villa account."],
            ['Airport transfers', 'car', 'photo-1449965408869-eaa3f722e40d', 'TRF-CMB', 'Air-conditioned car and a driver who knows the expressway and the back roads.',
                "Jaffna International Airport (Palaly) is about 30 minutes away; Colombo (BIA) transfers take 6–7 hours. Add a transfer when you book, or message us your flight number."],
            ['Excursions', 'globe', 'photo-1519046904884-53103b34b206', 'EXC-DELFT', 'Delft Island day trips, Jaffna Fort and Nallur Kandaswamy Kovil walks, Casuarina Beach and cooking classes.',
                "Our team arranges licensed guides and early starts. Prices are per person; children under 6 go free on most trips."],
            ['Laundry', 'sparkles', 'photo-1582719508461-905c673771fd', 'LAUN-WASH', 'Same-day wash and fold, pressing and dry cleaning.', 'Leave the bag in your wardrobe before 10am for same-day return.'],
            ['Private dining', 'star', 'photo-1517248135467-4c7edcad34c4', null, 'A candle-lit table on the beach or beside your pool, with a set menu from the chef.',
                'Popular for honeymoons and birthdays. Book at least 24 hours ahead.'],
        ];
        foreach ($services as $i => [$title, $icon, $image, $charge, $summary, $desc]) {
            $ci = $charge ? ChargeItem::where('code', $charge)->first() : null;
            SiteService::create(['title' => $title, 'slug' => \Str::slug($title), 'icon' => $icon, 'image' => $img($image), 'summary' => $summary, 'description' => $desc,
                'charge_item_id' => $ci?->id, 'price_from' => $ci?->price, 'sort_order' => $i]);
        }

        $gallery = [
            ['villas', 'Pool Villa at dusk', 'photo-1582719508461-905c673771fd'], ['villas', 'Ocean Pool Villa', 'photo-1571896349842-33c89424de2d'],
            ['villas', 'Garden Villa bedroom', 'photo-1590490360182-c33d57733427'], ['villas', 'Family Villa living room', 'photo-1493809842364-78817add7ffb'],
            ['dining', 'Terrace dinner', 'photo-1414235077428-338989a2e8c0'], ['dining', 'Dining room', 'photo-1517248135467-4c7edcad34c4'],
            ['dining', 'From the kitchen', 'photo-1504674900247-0877df9cc836'], ['grounds', 'The beach below the villas', 'photo-1507525428034-b723cf961d3e'],
            ['grounds', 'Pool court', 'photo-1566073771259-6a8506099945'], ['grounds', 'Morning in Jaffna', 'photo-1519046904884-53103b34b206'],
            ['spa', 'Spa pavilion', 'photo-1544161515-4ab6ce6db874'], ['spa', 'Treatment oils', 'photo-1540555700478-4be289fbecef'],
        ];
        foreach ($gallery as $i => [$cat, $title, $id]) {
            GalleryItem::create(['category' => $cat, 'title' => $title, 'type' => 'image', 'path' => $img($id, 1800), 'thumbnail' => $img($id, 700), 'sort_order' => $i]);
        }

        foreach ([
            ['Hannah & Leo', 'United Kingdom', 5, 'We came for four nights and stayed seven. The pool villa, the breakfast hoppers, the staff remembering how we take our coffee — perfect.', 'Google'],
            ['Anjali R.', 'India', 5, 'The family villa was ideal with two kids. Reception sorted a Delft Island day trip and an airport pickup in five minutes on WhatsApp.', 'Booking.com'],
            ['Markus W.', 'Germany', 5, 'Quiet, beautifully designed and the crab curry is the best we had in Sri Lanka.', 'TripAdvisor'],
            ['Sofia M.', 'Australia', 4, 'Gorgeous ocean villa and the most thoughtful team. Book the sunset dinner.', 'Airbnb'],
        ] as $i => [$n, $c, $r, $t, $s]) {
            Testimonial::create(['guest_name' => $n, 'country' => $c, 'rating' => $r, 'content' => $t, 'source' => $s, 'sort_order' => $i]);
        }
    }
}
