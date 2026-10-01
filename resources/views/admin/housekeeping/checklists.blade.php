@extends('layouts.admin')
@section('title', 'Cleaning checklists')
@section('content')
<x-page-header title="Cleaning checklists" :crumbs="['Housekeeping' => route('admin.housekeeping.index')]" sub="One item per line. Every item must be ticked before a task can be sent for inspection." />
<div class="grid cols-2">
    @foreach ($templates->concat([new \App\Models\ChecklistTemplate(['task_type' => 'adhoc', 'items' => [], 'is_active' => true])]) as $t)
        <form method="post" action="{{ route('admin.housekeeping.checklists.save') }}" class="card">
            @csrf
            @if ($t->exists)<input type="hidden" name="id" value="{{ $t->id }}">@endif
            <div class="card-head"><h2>{{ $t->exists ? $t->name : 'New checklist' }}</h2></div>
            <div class="card-body form-grid">
                <x-input name="name" label="Name" :value="$t->name" required col="f-8" :id="'n'.$t->id" />
                <x-select name="task_type" label="Used for" :options="\App\Models\HkTask::TYPES" :value="$t->task_type" col="f-4" :id="'t'.$t->id" />
                <x-textarea name="items" label="Items" :value="implode(PHP_EOL, $t->items ?? [])" rows="8" required :id="'i'.$t->id" />
            </div>
            <div class="card-foot"><button class="btn btn-primary" type="submit">Save</button></div>
        </form>
    @endforeach
</div>
@endsection
