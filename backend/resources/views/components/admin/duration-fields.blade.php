@props([
    'idPrefix',
    'isUnlimited' => false,
    'durationValue' => 1,
    'durationUnit' => 'month',
])

<div class="grid gap-3 sm:grid-cols-[1fr_6rem_8rem]" data-duration-form>
    <label class="grid gap-1.5 text-sm font-semibold text-slate-700">
        Durée
        <select name="duration_type" class="rounded-xl border border-slate-300 bg-white px-3 py-2.5 font-normal outline-none focus:border-amber-500 focus:ring-4 focus:ring-amber-100" data-duration-type>
            <option value="limited" @selected(! $isUnlimited)>Limitée</option>
            <option value="unlimited" @selected($isUnlimited)>Sans limite</option>
        </select>
    </label>
    <label class="grid gap-1.5 text-sm font-semibold text-slate-700">
        Valeur
        <input name="duration_value" type="number" min="1" max="10000" value="{{ $isUnlimited ? '' : $durationValue }}" @disabled($isUnlimited) class="rounded-xl border border-slate-300 px-3 py-2.5 font-normal outline-none disabled:bg-slate-100 disabled:text-slate-400 focus:border-amber-500 focus:ring-4 focus:ring-amber-100" data-duration-value>
    </label>
    <label class="grid gap-1.5 text-sm font-semibold text-slate-700">
        Unité
        <select name="duration_unit" @disabled($isUnlimited) class="rounded-xl border border-slate-300 bg-white px-3 py-2.5 font-normal outline-none disabled:bg-slate-100 disabled:text-slate-400 focus:border-amber-500 focus:ring-4 focus:ring-amber-100" data-duration-unit>
            <option value="day" @selected($durationUnit === 'day')>Jour(s)</option>
            <option value="month" @selected($durationUnit === 'month')>Mois</option>
            <option value="year" @selected($durationUnit === 'year')>Année(s)</option>
        </select>
    </label>
</div>
