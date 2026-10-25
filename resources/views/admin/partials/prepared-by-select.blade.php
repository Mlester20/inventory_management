{{-- Prepared By picker for forms that have no user list of their own: every login account plus the
     listed Encoders (Users page). $selected defaults to the signed-in user; $name/$id default to
     prepared_by. --}}
@php
    $preparerName = $name ?? 'prepared_by';
    $preparerId = $id ?? $preparerName;
    $preparerSelected = old($preparerName, $selected ?? auth()->id());
@endphp
<label for="{{ $preparerId }}" class="form-label">Prepared By</label>
<select name="{{ $preparerName }}" id="{{ $preparerId }}" class="form-select @error($preparerName) is-invalid @enderror">
    @foreach (\App\Models\User::preparers($selected ?? null)->get() as $preparer)
        <option value="{{ $preparer->id }}" @selected($preparerSelected == $preparer->id)>{{ $preparer->name }}</option>
    @endforeach
</select>
@error($preparerName)
    <div class="invalid-feedback d-block">{{ $message }}</div>
@enderror
