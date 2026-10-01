<div class="card-body form-grid">
    <x-input name="company_name" label="Company name" :value="$op->company_name" required col="f-6" />
    <x-input name="legal_name" label="Registered legal name" :value="$op->legal_name" col="f-6" />
    <x-input name="registration_no" label="Business registration no." :value="$op->registration_no" col="f-4" />
    <x-input name="tax_id" label="Tax / VAT ID" :value="$op->tax_id" col="f-4" />
    <x-input name="website" type="url" label="Website" :value="$op->website" col="f-4" />
    <x-input name="contact_name" label="Main contact" :value="$op->contact_name" required col="f-4" />
    <x-input name="email" type="email" label="Reservations email" :value="$op->email" required col="f-4" />
    <x-input name="phone" label="Phone" :value="$op->phone" col="f-4" />
    <x-input name="address" label="Address" :value="$op->address" col="f-8" />
    <x-input name="country" label="Country" :value="$op->country" col="f-4" />
    <x-textarea name="bank_details" label="Bank details (for refunds / commission)" :value="$op->bank_details" rows="2" col="f-6" />
    <div class="field f-6"><label for="logo">Logo</label><input id="logo" type="file" name="logo" accept="image/png,image/jpeg,image/webp">
        @if($op->logo_path)<img src="{{ asset('storage/'.$op->logo_path) }}" alt="" style="max-height:40px;margin-top:6px">@endif</div>
    @if (! empty($admin))
        <x-input name="credit_limit" type="number" step="0.01" min="0" label="Credit limit" :value="$op->credit_limit" col="f-4" />
        <x-input name="payment_terms_days" type="number" min="0" label="Payment terms (days)" :value="$op->payment_terms_days" col="f-4" />
        <x-textarea name="notes" label="Internal notes" :value="$op->notes" rows="2" col="f-12" />
    @endif
</div>
