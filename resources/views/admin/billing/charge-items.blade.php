@extends('layouts.admin')
@section('title', 'Service charges')
@section('content')
<x-page-header title="Guest service charges" sub="The catalogue staff use to post laundry, spa, transport, airport transfers, minibar and other services to a guest's folio. Restaurant checks post automatically from the POS.">
    <a class="btn btn-primary" href="{{ route('admin.charge-items.create') }}"><x-icon name="plus" /> New service</a>
</x-page-header>
<div class="grid cols-2">
    @foreach ($items as $dept => $list)
        <div class="card">
            <div class="card-head"><h2>{{ config('vaasal.departments.'.$dept, $dept) }}</h2><span class="small muted">{{ $list->count() }}</span></div>
            <div class="table-wrap"><table class="table">
                <tbody>
                @foreach ($list as $ci)
                    <tr @class(['strike' => ! $ci->is_active])>
                        <td>{{ $ci->name }}<div class="small muted mono">{{ $ci->code }}</div></td>
                        <td class="small">{{ $ci->taxable ? 'Taxable' : 'No tax' }}{{ $ci->service_chargeable ? ' · +service' : '' }}</td>
                        <td class="num">{{ money($ci->price) }}</td>
                        <td class="actions"><a class="btn btn-sm btn-ghost" href="{{ route('admin.charge-items.edit', $ci) }}"><x-icon name="edit" /></a></td>
                    </tr>
                @endforeach
                </tbody>
            </table></div>
        </div>
    @endforeach
</div>
@endsection
