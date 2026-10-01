@php $p = $prefix ?? 'new'; @endphp
<x-input name="name" label="Name" :value="$d->name" required col="f-12" :id="$p.'-name'" placeholder="e.g. Villa G1 door, Staff entrance clock" />
<x-select name="type" label="Reader type" :options="\App\Models\AttendanceDevice::TYPES" :value="$d->type" col="f-6" :id="$p.'-type'" />
<x-select name="purpose" label="Purpose" :options="\App\Models\AttendanceDevice::PURPOSES" :value="$d->purpose" col="f-6" :id="$p.'-purpose'" />
<x-select name="villa_id" label="Guards villa door" :options="$villas" :value="$d->villa_id" placeholder="— none —" col="f-6" :id="$p.'-villa'" />
<div class="field f-6"><label for="{{ $p }}-zone">Zone</label>
    <input id="{{ $p }}-zone" name="zone" value="{{ old('zone', $d->zone) }}" list="{{ $p }}-zones" maxlength="60" placeholder="e.g. Villa Area">
    <datalist id="{{ $p }}-zones">@foreach ($zones as $z)<option value="{{ $z }}">@endforeach</datalist></div>
<x-input name="location" label="Location note" :value="$d->location" col="f-6" :id="$p.'-loc'" />
<x-select name="mode" label="Mode" :options="['hardware' => 'Physical reader', 'simulator' => 'Simulator (development)']" :value="$d->mode" col="f-6" :id="$p.'-mode'" />
