<?php
// Single source for the Tamil training video: storyboard, narration, SRT timing and slideshow frame list.
// Each narration line = [screenshot shown while the line is spoken, Tamil narration, on-screen action / pointer].
return [
    ['id' => 1, 'title' => 'அறிமுகம்', 'en' => 'Welcome', 'lines' => [
        ['14_login_page', 'வணக்கம்! Vaasal Villa ஹோட்டல் மற்றும் உணவக மேலாண்மை அமைப்பின் பயிற்சிக்கு வரவேற்கிறோம்.', 'Logo + title card, then the sign-in page'],
        ['14_login_page', 'இந்த அமைப்பில் இரண்டு பகுதிகள் உள்ளன: வில்லாக்களுக்கான ஹோட்டல் PMS, உணவகத்துக்கான POS.', 'Highlight "Hotel PMS" and "Restaurant POS" labels'],
        ['14_login_page', 'இன்று ஒரு விருந்தினரின் முழுப் பயணத்தையும் — முன்பதிவு முதல் வெளியேறுதல் வரை — பார்ப்போம்.', 'Show the five examples A–E as a list'],
    ]],
    ['id' => 2, 'title' => 'உள்நுழைவு', 'en' => 'Signing in', 'lines' => [
        ['14_login_page', 'உங்கள் பயனர் பெயரையும் கடவுச்சொல்லையும் உள்ளிட்டு Sign in அழுத்துங்கள்.', 'Pointer on Username, Password, Sign in'],
        ['14_login_page', 'கீழே உள்ள டெமோ அட்டைகள் சோதனைக் கணினியில் மட்டுமே தெரியும்; உண்மையான கணினியில் மறைந்துவிடும்.', 'Circle the "Local demo only" badge'],
        ['77_invalid_login', 'கடவுச்சொல் தவறானால் பொதுவான செய்தி வரும். ஐந்து தவறுகளுக்குப் பின் கணக்கு 15 நிமிடம் பூட்டப்படும்.', 'Zoom on the red error box'],
        ['77_invalid_login', 'உங்கள் கடவுச்சொல்லை யாருடனும் பகிர வேண்டாம்; மேசையை விட்டுச் செல்லும்போது Log out செய்யுங்கள்.', 'Text overlay: "கடவுச்சொல் இரகசியம்"'],
    ]],
    ['id' => 3, 'title' => 'டாஷ்போர்டு மற்றும் முன் மேசை', 'en' => 'Dashboard & front desk', 'lines' => [
        ['01_admin_dashboard', 'டாஷ்போர்டில் இன்றைய வருகைகள், வெளியேற்றங்கள், நிரம்பல், வருமானம் ஒரே பார்வையில் தெரியும்.', 'Pan across the KPI cards'],
        ['01_admin_dashboard', 'கீழே வில்லா நிலைப் பலகை — ஒவ்வொரு வில்லாவும் காலியா, நிரம்பியதா, சுத்தம் தேவையா என்று காட்டும்.', 'Pan to the Villa status board'],
        ['15_front_desk', 'வரவேற்பாளரின் முதன்மைப் பக்கம் Front desk. இங்கே Walk-in மற்றும் Run night audit பொத்தான்கள் உள்ளன.', 'Pointer on Walk-in and Run night audit'],
    ]],
    ['id' => 4, 'title' => 'Walk-in முன்பதிவு — உதாரணம் A', 'en' => 'Walk-in booking (Example A)', 'lines' => [
        ['02_walkin_booking', 'Nuwan Perera முன்பதிவின்றி வருகிறார்: 29 செப்டம்பர் முதல் 3 அக்டோபர் வரை, இருவர்.', 'Show dates 29/09/2026 → 03/10/2026'],
        ['03_room_selection', 'அனைத்து இரவுகளிலும் காலியான வில்லாக்கள் மட்டுமே பட்டியலில் வரும். G1-ஐத் தேர்வு செய்கிறோம்.', 'Tick G1; Adults 2, Children 0'],
        ['02_walkin_booking', 'விருந்தினரின் பெயர், நாடு, மின்னஞ்சல், தொலைபேசி ஆகியவற்றை நிரப்புங்கள்.', 'Type the guest details'],
        ['02_walkin_booking', 'மேலாளர் ஒப்புக்கொண்ட இரவு விலை 30,500 — காரணத்துடன் override செய்யப்படுகிறது.', 'Open "Override nightly rate", type 30500 and the reason'],
        ['02_walkin_booking', 'முற்பணம் இருபதாயிரம் ரூபாய், அட்டை மூலம். பின்னர் Create booking.', 'Deposit 20000 · Card · Create booking'],
        ['04_checkin', 'முன்பதிவு மொத்தம் 144,936 ரூபாய்: அறை 122,000, சேவைக் கட்டணம் 10%, வரி 8%.', 'Overlay the calculation table'],
    ]],
    ['id' => 5, 'title' => 'வருகைப் பதிவு மற்றும் சாவி அட்டை', 'en' => 'Check-in & key card', 'lines' => [
        ['04_checkin', 'Check-in பக்கத்தில் அடையாள ஆவண வகையும் எண்ணும் கட்டாயம். ஆவணப் படத்தையும் இணைக்கலாம்.', 'Pointer on Document and Document number'],
        ['04_checkin', 'வில்லா Ready நிலையில் இருக்க வேண்டும். Free cards பட்டியலில் ஒரு அட்டையைத் தேர்வு செய்யுங்கள்.', 'Click the first free card chip'],
        ['26_checked_in', 'Complete check-in அழுத்தியதும் வில்லா Occupied ஆகும், அட்டை Active ஆகும்.', 'Show "Checked in" badge and the active card'],
        ['26_checked_in', 'கவனிக்க: இந்தக் கணினியில் கதவுப் பூட்டுகள் சிமுலேட்டர் மூலம் மட்டுமே இயங்குகின்றன.', 'Purple overlay: "SIMULATION"'],
    ]],
    ['id' => 6, 'title' => 'அறை சேவை — உதாரணம் C', 'en' => 'Room service (Example C)', 'lines' => [
        ['27_room_service_select', 'விருந்தினர் வில்லாவிலிருந்து உணவு கேட்கிறார். POS-இல் Room service அழுத்தி G1 · Nuwan Perera தேர்வு செய்யுங்கள்.', 'Open Room service; choose G1'],
        ['28_room_service_check', 'Seafood linguine, Crab soup — மொத்தம் 8,500 ரூபாய். Send அழுத்தினால் சமையலறைக்குச் செல்லும்.', 'Add the two dishes; press Send'],
        ['07_kitchen_kot', 'சமையலறைத் திரையில் புதிய ticket வருகிறது. Start, Ready, Served என வரிசையாக அழுத்துங்கள்.', 'Highlight the Villa G1 ticket and its button'],
        ['08_room_service', 'பின்னர் Pay, Charge to villa. சேவைக் கட்டணம், வரியுடன் 10,098 ரூபாய் G1 கணக்கில் சேரும்.', 'Choose "Charge to villa"; Complete payment'],
    ]],
    ['id' => 7, 'title' => 'Folio மற்றும் கூடுதல் கட்டணம்', 'en' => 'Folio & laundry charge', 'lines' => [
        ['29_laundry_charge', 'சலவைக் கட்டணம் சேர்க்க Folio-வில் Post charge. Pressing, ஐந்து உடைகள் — இரண்டாயிரம் ரூபாய்.', 'Select the laundry item; Quantity 5'],
        ['09_guest_folio', 'வரி 160 சேர்த்து 2,160. Folio-வில் அறை சேவை, சலவை, முற்பணம் எல்லாம் தெரியும்.', 'Scroll the folio lines'],
        ['09_guest_folio', 'பதிவான கட்டணத்தை அழிக்க முடியாது; அனுமதியுள்ளவர் மட்டும் எதிர்ப் பதிவு செய்யலாம்.', 'Text overlay: "Reverse, don\'t delete"'],
    ]],
    ['id' => 8, 'title' => 'வெளியேறுதல் — உதாரணம் D', 'en' => 'Checkout (Example D)', 'lines' => [
        ['16_checkout_blocked', 'Oliver Jensen வெளியேறுகிறார். ஆனால் ஒரு உணவகக் கணக்கு இன்னும் திறந்துள்ளது — Complete checkout முடக்கப்பட்டுள்ளது.', 'Red box; disabled button'],
        ['17_settlement_requested', 'Request restaurant settlement அழுத்துங்கள். காசாளருக்கு அறிவிப்பு செல்லும்.', 'Click Request restaurant settlement'],
        ['19_pos_charge_to_villa', 'காசாளர் அந்த check-ஐ Charge to villa மூலம் வில்லாக் கணக்கில் சேர்க்கிறார்.', 'Cashier screen: Charge to villa'],
        ['10_checkout', 'இப்போது நிலுவையை அட்டையால் செலுத்தி Complete checkout அழுத்துங்கள்.', 'Settle with Card; Complete checkout'],
        ['21_final_invoice', 'இறுதி விலைப்பட்டியல்: 84,371.76 ரூபாய், முழுமையாகச் செலுத்தப்பட்டது. வில்லா Dirty ஆகி சுத்தப் பணி உருவாகிறது.', 'Show PAID stamp'],
    ]],
    ['id' => 9, 'title' => 'வீட்டுப் பராமரிப்பு — உதாரணம் E', 'en' => 'Housekeeping (Example E)', 'lines' => [
        ['22_my_cleaning_tasks', 'அறைப் பணியாளர் My cleaning tasks-இல் G1-ஐ Accept செய்து Start அழுத்துகிறார்.', 'Accept → Start'],
        ['23_cleaning_checklist', 'சரிபார்ப்புப் பட்டியலில் ஒவ்வொரு வேலையையும் டிக் செய்யுங்கள். எல்லாம் முடியாமல் சமர்ப்பிக்க முடியாது.', 'Tick checklist items'],
        ['11_housekeeping', 'மேற்பார்வையாளரின் board-இல் G1 ஆய்வுக்காகக் காத்திருக்கிறது.', 'Highlight G1 "Awaiting inspection"'],
        ['24_inspection', 'சரியாக இருந்தால் Approve; குறை இருந்தால் காரணத்துடன் Reject.', 'Pointer on Approve and Reject'],
        ['25_villa_ready', 'G1 இப்போது Ready — அடுத்த விருந்தினருக்குத் தயார்.', 'Green "Ready" status'],
    ]],
    ['id' => 10, 'title' => 'உணவகம்: மேசை T6 — உதாரணம் B', 'en' => 'Restaurant table T6 (Example B)', 'lines' => [
        ['30_pos_floor_plan', 'பணியாளர் மேசை வரைபடத்தில் காலியான T6-ஐத் தேர்வு செய்து, விருந்தினர் எண்ணிக்கை நான்கு.', 'Tap T6; covers 4'],
        ['05_pos_table', 'உணவுகளைச் சேர்த்து Send. சமையலறைக்கும் bar-க்கும் தனித்தனி ticket செல்லும்.', 'Show the order lines; press Send'],
        ['31_kitchen_kot_t6', 'சமையலறை தயாரித்து Ready, பின்னர் Served எனக் குறிக்கிறது.', 'T6 tickets on the KDS'],
        ['06_pos_payment', 'பில் 27,086.40. விருந்தினர் முப்பதாயிரம் கொடுக்கிறார் — மீதி 2,913.60 தானாகக் கணக்கிடப்படுகிறது.', 'Type 30000 in Tendered; show Change'],
        ['36_table_released', 'Complete payment அழுத்தியதும் பற்றுச்சீட்டு வரும்; T6 மீண்டும் காலியாகும்.', 'T6 free again'],
    ]],
    ['id' => 11, 'title' => 'காசாளர் ஷிப்ட் மற்றும் நாள் முடிவு', 'en' => 'Cashier shift & day-end', 'lines' => [
        ['32_open_shift', 'பணம் வாங்கும் முன் அந்த outlet-இல் ஷிப்ட் திறக்க வேண்டும் — தொடக்கப் பணம் பத்தாயிரம்.', 'Outlet Vaasal Kitchen; float 10000; Open shift'],
        ['81_day_end_count', 'நாள் முடிவில் drawer-இல் உள்ள பணத்தை எண்ணி Counted cash-இல் உள்ளிடுங்கள். வேறுபாடு தானாகத் தெரியும்.', 'Denomination grid and Variance box'],
        ['82_shift_closed', 'உறுதிப்படுத்தி Close shift. ஷிப்ட் பூட்டப்பட்டு மேலாளர் மீளாய்வுக்குச் செல்லும்.', 'Shift closed banner'],
    ]],
    ['id' => 12, 'title' => 'சாவி அட்டைகள் (சிமுலேஷன்)', 'en' => 'Key cards (simulation)', 'lines' => [
        ['12_key_cards', 'Key cards பக்கத்தில் விருந்தினர், பணியாளர் அட்டைகளை நிர்வகிக்கலாம்; தொலைந்தால் உடனே Lost எனக் குறியுங்கள்.', 'Pointer on Lost and Revoke'],
        ['47_rfid_simulator', 'சிமுலேட்டரில் Nuwan-இன் அட்டை G1 கதவில் — Access Granted. இது சோதனை மட்டும்; உண்மையான பூட்டு இணைக்கப்படவில்லை.', 'Purple overlay: "SIMULATION ONLY"'],
    ]],
    ['id' => 13, 'title' => 'இரவுத் தணிக்கை', 'en' => 'Night audit', 'lines' => [
        ['49_night_audit_confirm', 'மேலாளர் Front desk-இல் Run night audit அழுத்தி உறுதிப்படுத்துகிறார்.', 'Confirm dialog'],
        ['13_night_audit', 'அறை இரவுக் கட்டணங்கள் பதியும், வராதவர்கள் No-show ஆவார்கள், காலாவதியான அட்டைகள் ரத்தாகும்.', 'Zoom on the result message'],
        ['50_folio_after_audit', 'Nuwan-இன் folio-வில் 29 செப்டம்பர் இரவுக் கட்டணம் 36,234 சேர்ந்துள்ளது. இரண்டாம் முறை இயக்கினாலும் இரட்டிப்பாகாது.', 'Highlight the new room-night line'],
    ]],
    ['id' => 14, 'title' => 'அறிக்கைகள் மற்றும் உணவக மேலாளர்', 'en' => 'Reports & restaurant back office', 'lines' => [
        ['60_reports', 'Reports பக்கத்தில் 15 வகை அறிக்கைகள்; தேதி வரம்பு கொடுத்து CSV-ஆக எடுக்கலாம்.', 'Scroll the report list'],
        ['38_pos_dashboard', 'உணவக மேலாளர் POS dashboard-இல் விற்பனை, திறந்த checks, ஷிப்ட் நிலையைப் பார்க்கிறார்.', 'Pan across the POS dashboard'],
    ]],
    ['id' => 15, 'title' => 'பிழைகள் மற்றும் தீர்வுகள்', 'en' => 'Errors & troubleshooting', 'lines' => [
        ['75_session_ended', '30 நிமிடம் எதுவும் செய்யாவிட்டால் அமர்வு முடியும். மீண்டும் உள்நுழைந்தால் அதே பக்கத்துக்குத் திரும்புவீர்கள்.', 'Show the session-ended message'],
        ['73_forbidden_403', '403 என்றால் உங்கள் பாத்திரத்துக்கு அனுமதி இல்லை. சரியான கணக்கில் உள்நுழையுங்கள்.', 'Show the 403 page'],
    ]],
    ['id' => 16, 'title' => 'நிறைவு', 'en' => 'Closing', 'lines' => [
        ['78_website_home', 'முழு விவரங்களுக்கு தமிழ்ப் பயன்பாட்டு வழிகாட்டி PDF-ஐப் பாருங்கள்.', 'Show the manual cover'],
        ['78_website_home', 'உதவிக்கு: 0764413420, aflalal2004@gmail.com. நன்றி!', 'Contact card + logo'],
    ]],
];
