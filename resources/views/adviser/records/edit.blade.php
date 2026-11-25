@extends('layouts.app')

@section('title', 'Attendance & Core Values')
@section('header', 'Attendance & DepEd Observed Values — ' . $section->gradeLevel->name . ' ' . $section->name)
@section('subheader', 'SY ' . $section->schoolYear->name . ' • Printed on learner report cards when enabled.')

@section('content')
<div class="space-y-6">
    <div class="flex flex-wrap items-center justify-between gap-3">
        <a href="{{ route('adviser.sections.show', $section) }}" class="text-xs font-semibold text-slate-600 hover:text-slate-900">&larr; Back to Advisory Section</a>
        <form method="GET" action="{{ route('adviser.records.edit', $section) }}" class="flex flex-wrap items-center gap-2">
            <select name="month" onchange="this.form.submit()" class="rounded-lg border-slate-300 text-xs">
                @for($m = 1; $m <= 12; $m++)
                    <option value="{{ $m }}" @selected($month === $m)>{{ \Carbon\Carbon::create(2026, $m, 1)->format('F') }}</option>
                @endfor
            </select>
            <input type="number" name="year" value="{{ $year }}" min="2020" max="2100" class="w-20 rounded-lg border-slate-300 text-xs">
            <select name="period_id" onchange="this.form.submit()" class="rounded-lg border-slate-300 text-xs">
                @foreach($periods as $p)
                    <option value="{{ $p->id }}" @selected($selectedPeriod?->id === $p->id)>{{ $p->name }} ({{ $p->code }})</option>
                @endforeach
            </select>
            <button type="submit" class="px-3 py-1.5 rounded-lg bg-slate-800 text-white text-xs font-semibold">Load</button>
            <a href="{{ route('adviser.records.export-attendance', $section) }}"
               class="px-3 py-1.5 rounded-lg bg-emerald-600 hover:bg-emerald-700 text-white text-xs font-semibold">
                Export Attendance (.xlsx)
            </a>
        </form>
    </div>

    {{-- Monthly Attendance Form with Bulk Fill Helpers --}}
    <form method="POST" action="{{ route('adviser.records.attendance.save', $section) }}"
          x-data="{
              bulkDays: 20,
              applySchoolDays() {
                  const val = Math.max(0, Math.min(31, Number(this.bulkDays || 0)));
                  this.$el.querySelectorAll('input[data-att=\'school_days\']').forEach(el => {
                      el.value = val;
                      this.recalcRow(el.dataset.row);
                  });
              },
              applyPerfectAttendance() {
                  const val = Math.max(0, Math.min(31, Number(this.bulkDays || 0)));
                  this.$el.querySelectorAll('tr[data-att-row]').forEach(tr => {
                      tr.querySelector('input[data-att=\'school_days\']').value = val;
                      tr.querySelector('input[data-att=\'days_present\']').value = val;
                      tr.querySelector('input[data-att=\'days_absent\']').value = 0;
                      tr.querySelector('input[data-att=\'days_tardy\']').value = 0;
                  });
              },
              recalcRow(rowId) {
                  const tr = this.$el.querySelector('tr[data-att-row=\'' + rowId + '\']');
                  if (!tr) return;
                  const days = parseFloat(tr.querySelector('input[data-att=\'school_days\']').value || '0');
                  const present = parseFloat(tr.querySelector('input[data-att=\'days_present\']').value || '0');
                  const absent = Math.max(0, Math.round((days - present) * 2) / 2);
                  tr.querySelector('input[data-att=\'days_absent\']').value = absent;
              }
          }"
          class="school-card overflow-hidden">
        @csrf
        <input type="hidden" name="month_number" value="{{ $month }}">
        <input type="hidden" name="year_number" value="{{ $year }}">

        <div class="px-6 py-4 bg-slate-50 border-b border-slate-200 flex flex-wrap items-center justify-between gap-3">
            <div>
                <h2 class="text-base font-bold text-slate-900">Monthly Attendance &mdash; {{ \Carbon\Carbon::create($year, $month, 1)->format('F Y') }}</h2>
                <p class="text-xs text-slate-500">Editing Present Days automatically updates Absent Days.</p>
            </div>
            <div class="flex flex-wrap items-center gap-2 text-xs">
                <label class="text-slate-600 font-semibold">School Days:</label>
                <input type="number" step="0.5" min="0" max="31" x-model="bulkDays" class="w-16 text-center rounded-lg border-slate-300 text-xs py-1">
                <button type="button" @click="applySchoolDays()" class="px-3 py-1.5 rounded-lg bg-white border border-slate-300 hover:bg-slate-100 text-slate-700 font-semibold">
                    Apply Days to All
                </button>
                <button type="button" @click="applyPerfectAttendance()" class="px-3 py-1.5 rounded-lg bg-emerald-50 border border-emerald-200 hover:bg-emerald-100 text-emerald-800 font-semibold">
                    Fill Perfect Attendance
                </button>
            </div>
        </div>

        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-slate-200 text-xs">
                <thead class="bg-slate-50 text-left font-semibold uppercase tracking-wider text-slate-500">
                <tr>
                    <th class="px-5 py-3">Learner</th>
                    <th class="px-4 py-3 text-center">School Days</th>
                    <th class="px-4 py-3 text-center">Present</th>
                    <th class="px-4 py-3 text-center">Absent</th>
                    <th class="px-4 py-3 text-center">Tardy</th>
                </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 bg-white">
                @foreach($enrollments as $enr)
                    @php $att = $attendanceByEnrollment->get($enr->id); @endphp
                    <tr data-att-row="{{ $enr->id }}" class="hover:bg-slate-50/70">
                        <td class="px-5 py-2.5 font-medium text-slate-900">{{ $enr->student->formal_name }}</td>
                        <td class="px-4 py-2 text-center">
                            <input type="number" step="0.5" min="0" max="31"
                                   data-att="school_days" data-row="{{ $enr->id }}"
                                   @input="recalcRow('{{ $enr->id }}')"
                                   name="attendance[{{ $enr->id }}][school_days]"
                                   value="{{ $att?->school_days ?? 20 }}" required
                                   class="w-20 text-center rounded border-slate-300 text-xs">
                        </td>
                        <td class="px-4 py-2 text-center">
                            <input type="number" step="0.5" min="0" max="31"
                                   data-att="days_present" data-row="{{ $enr->id }}"
                                   @input="recalcRow('{{ $enr->id }}')"
                                   name="attendance[{{ $enr->id }}][days_present]"
                                   value="{{ $att?->days_present ?? 20 }}" required
                                   class="w-20 text-center rounded border-slate-300 text-xs">
                        </td>
                        <td class="px-4 py-2 text-center">
                            <input type="number" step="0.5" min="0" max="31"
                                   data-att="days_absent" data-row="{{ $enr->id }}"
                                   name="attendance[{{ $enr->id }}][days_absent]"
                                   value="{{ $att?->days_absent ?? 0 }}" required
                                   class="w-20 text-center rounded border-slate-300 text-xs bg-slate-50">
                        </td>
                        <td class="px-4 py-2 text-center">
                            <input type="number" step="0.5" min="0" max="31"
                                   data-att="days_tardy" data-row="{{ $enr->id }}"
                                   name="attendance[{{ $enr->id }}][days_tardy]"
                                   value="{{ $att?->days_tardy ?? 0 }}" required
                                   class="w-20 text-center rounded border-slate-300 text-xs">
                        </td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
        <div class="px-6 py-3 bg-slate-50 border-t border-slate-200 flex justify-end">
            <button type="submit" class="px-5 py-2 rounded-xl bg-school-600 hover:bg-school-700 text-white text-xs font-semibold shadow-sm">
                Save Attendance Records
            </button>
        </div>
    </form>

    {{-- DepEd Observed Core Values Form with Bulk Fill & Legend --}}
    @if($selectedPeriod)
        <form method="POST" action="{{ route('adviser.records.values.save', $section) }}"
              x-data="{
                  showLegend: false,
                  fillValues(code, onlyEmpty) {
                      this.$el.querySelectorAll('select[data-val-select]').forEach(sel => {
                          if (!onlyEmpty || sel.value === '') {
                              sel.value = code;
                          }
                      });
                  }
              }"
              class="school-card overflow-hidden">
            @csrf
            <input type="hidden" name="grading_period_id" value="{{ $selectedPeriod->id }}">

            <div class="px-6 py-4 bg-slate-50 border-b border-slate-200 flex flex-wrap items-center justify-between gap-3">
                <div>
                    <h2 class="text-base font-bold text-slate-900">DepEd Observed Core Values &mdash; {{ $selectedPeriod->name }}</h2>
                    <p class="text-xs text-slate-500">AO = Always Observed, SO = Sometimes Observed, RO = Rarely Observed, NO = Not Observed</p>
                </div>
                <div class="flex flex-wrap items-center gap-2 text-xs">
                    <button type="button" @click="fillValues('AO', true)" class="px-3 py-1.5 rounded-lg bg-emerald-50 border border-emerald-200 hover:bg-emerald-100 text-emerald-800 font-semibold">
                        Fill Empty &rarr; AO
                    </button>
                    <button type="button" @click="fillValues('AO', false)" class="px-3 py-1.5 rounded-lg bg-white border border-slate-300 hover:bg-slate-100 text-slate-700 font-semibold">
                        Set All &rarr; AO
                    </button>
                    <button type="button" @click="fillValues('SO', false)" class="px-3 py-1.5 rounded-lg bg-white border border-slate-300 hover:bg-slate-100 text-slate-700 font-semibold">
                        Set All &rarr; SO
                    </button>
                    <button type="button" @click="showLegend = !showLegend" class="px-3 py-1.5 rounded-lg bg-school-50 border border-school-200 hover:bg-school-100 text-school-800 font-semibold">
                        <span x-text="showLegend ? 'Hide Statements' : 'Behavior Legend'"></span>
                    </button>
                </div>
            </div>

            <div x-show="showLegend" x-cloak class="px-6 py-4 bg-school-50/40 border-b border-slate-200 grid grid-cols-1 md:grid-cols-2 gap-2 text-xs">
                @foreach($statements as $code => $meta)
                    <div class="p-2.5 rounded-lg bg-white border border-slate-200">
                        <span class="font-bold text-school-700 font-mono">{{ $code }}</span>
                        <span class="font-semibold text-slate-800 ml-1">({{ $meta['core_label'] }})</span>
                        <p class="text-slate-600 mt-0.5">{{ $meta['statement'] }}</p>
                    </div>
                @endforeach
            </div>

            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-slate-200 text-xs">
                    <thead class="bg-slate-50 text-left font-semibold uppercase tracking-wider text-slate-500">
                    <tr>
                        <th class="px-4 py-3">Learner</th>
                        @foreach($statements as $code => $meta)
                            <th class="px-2 py-3 text-center" title="{{ $meta['core_label'] }}: {{ $meta['statement'] }}">{{ $code }}</th>
                        @endforeach
                    </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 bg-white">
                    @foreach($enrollments as $enr)
                        <tr class="hover:bg-slate-50/70">
                            <td class="px-4 py-2 font-medium text-slate-900 whitespace-nowrap">{{ $enr->student->formal_name }}</td>
                            @foreach($statements as $code => $meta)
                                @php $obs = $valuesByKey->get($enr->id . ':' . $code); @endphp
                                <td class="px-2 py-1.5 text-center">
                                    <select data-val-select name="values[{{ $enr->id }}][{{ $code }}]" class="rounded border-slate-300 text-xs py-1">
                                        <option value="">—</option>
                                        @foreach($ratingScale as $rCode => $rLabel)
                                            <option value="{{ $rCode }}" @selected($obs?->rating === $rCode)>{{ $rCode }}</option>
                                        @endforeach
                                    </select>
                                </td>
                            @endforeach
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
            <div class="px-6 py-3 bg-slate-50 border-t border-slate-200 flex justify-end">
                <button type="submit" class="px-5 py-2 rounded-xl bg-school-600 hover:bg-school-700 text-white text-xs font-semibold shadow-sm">
                    Save Observed Values
                </button>
            </div>
        </form>
    @endif
</div>
@endsection
