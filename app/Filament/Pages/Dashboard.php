<?php

namespace App\Filament\Pages;

use App\Models\AcademicYear;
use App\Models\Course;
use App\Models\Subject;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Pages\Dashboard as BaseDashboard;
use Filament\Pages\Dashboard\Concerns\HasFiltersForm;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class Dashboard extends BaseDashboard
{
    use HasFiltersForm;

    public function filtersForm(Schema $schema): Schema
    {
        return $schema->components([
            Section::make(__('dashboard.fields.filters'))
                ->schema([
                    DatePicker::make('startDate')
                        ->label(__('dashboard.fields.from_date')),
                    DatePicker::make('endDate')
                        ->label(__('dashboard.fields.to_date'))
                        ->afterOrEqual('startDate'),
                    Select::make('academicYearId')
                        ->label(__('dashboard.fields.academic_year'))
                        ->options(fn (): array => AcademicYear::query()
                            ->orderBy('sort_order')
                            ->get()
                            ->mapWithKeys(fn (AcademicYear $year): array => [$year->id => $year->localized('title', 'ar')])
                            ->all()),
                    Select::make('subjectId')
                        ->label(__('dashboard.fields.subject'))
                        ->options(fn (): array => Subject::query()
                            ->orderBy('sort_order')
                            ->get()
                            ->mapWithKeys(fn (Subject $subject): array => [$subject->id => $subject->localized('title', 'ar')])
                            ->all()),
                    Select::make('courseId')
                        ->label(__('dashboard.fields.course'))
                        ->options(fn (): array => Course::query()
                            ->orderBy('id')
                            ->get()
                            ->mapWithKeys(fn (Course $course): array => [$course->id => $course->localizedTitle('ar')])
                            ->all()),
                ])
                ->columns([
                    'default' => 1,
                    'md' => 3,
                    'xl' => 5,
                ]),
        ]);
    }
}
