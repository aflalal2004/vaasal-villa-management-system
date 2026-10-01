@extends('layouts.admin')
@section('title', 'Departments & job roles')
@section('content')
<x-page-header title="Departments & job roles" :crumbs="['Employees' => route('admin.staff.employees.index')]" sub="A job role can suggest a default system role when a login is created." />
<div class="grid cols-main">
    <div class="stack">
        @foreach ($departments as $d)
            <div class="card">
                <div class="card-head"><h2>{{ $d->name }} <span class="mono small muted">{{ $d->code }}</span></h2><span class="small muted">{{ $d->employees_count }} employee(s)</span></div>
                <ul class="list">@forelse ($d->jobRoles as $j)<li>{{ $j->title }} <span class="spacer"></span><span class="small muted">{{ $j->defaultRole?->name ?? 'no default login role' }}</span></li>@empty<li class="muted small">No job roles yet.</li>@endforelse</ul>
            </div>
        @endforeach
    </div>
    <div class="stack" style="align-content:start">
        <form method="post" action="{{ route('admin.staff.departments.store') }}" class="card">
            @csrf
            <div class="card-head"><h2>New department</h2></div>
            <div class="card-body form-grid"><x-input name="code" label="Code" required col="f-4" /><x-input name="name" label="Name" required col="f-8" /><x-input name="description" label="Description" col="f-12" /></div>
            <div class="card-foot"><button class="btn btn-sm btn-primary" type="submit">Add</button></div>
        </form>
        <form method="post" action="{{ route('admin.staff.job-roles.store') }}" class="card">
            @csrf
            <div class="card-head"><h2>New job role</h2></div>
            <div class="card-body form-grid">
                <x-select name="department_id" label="Department" :options="$departments->pluck('name', 'id')" required col="f-12" />
                <x-input name="title" label="Title" required col="f-12" />
                <x-select name="default_role_id" label="Default system role" :options="$roles" placeholder="None" col="f-12" />
            </div>
            <div class="card-foot"><button class="btn btn-sm btn-primary" type="submit">Add</button></div>
        </form>
    </div>
</div>
@endsection
