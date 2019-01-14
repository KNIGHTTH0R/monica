<?php

namespace App\Http\Controllers\Contacts;

use Carbon\Carbon;
use Illuminate\Http\Request;
use App\Models\Contact\Contact;
use App\Models\Contact\Activity;
use App\Http\Controllers\Controller;
use App\Models\Contact\ActivityType;
use App\Services\ActivityStatisticService;
use App\Services\Activity\Activity\CreateActivity;
use App\Services\Activity\Activity\DestroyActivity;
use App\Services\Activity\Activity\AttachContactToActivity;
use App\Http\Resources\Activity\Activity as ActivityResource;

class ActivitiesController extends Controller
{
    /**
     * Statistics about an activity.
     *
     * @var ActivityStatisticService
     */
    protected $activityStatisticService;

    public function __construct(ActivityStatisticService $service)
    {
        $this->activityStatisticService = $service;
    }

    /**
     * Get the list of activities.
     *
     * @param Request $request
     * @param Contact $contact
     * @return \Illuminate\Http\Response
     */
    public function index(Request $request, Contact $contact)
    {
        $activities = $contact->activities()->orderBy('happened_at', 'desc')->get();

        return ActivityResource::collection($activities);
    }

    /**
     * Store an activity.
     *
     * @param  Contact $contact
     * @return Activity
     */
    public function store(Request $request, Contact $contact)
    {
        $activity = (new CreateActivity)->execute([
            'account_id' => auth()->user()->account->id,
            'activity_type_id' => $request->get('activity_type_id'),
            'summary' => $request->get('summary'),
            'description' => $request->get('description'),
            'date' => $request->get('happened_at'),
        ]);

        return (new AttachContactToActivity)->execute([
            'account_id' => auth()->user()->account->id,
            'activity_id' => $activity->id,
            'contacts' => [$contact->id],
        ]);
    }

    /**
     * Delete the activity.
     *
     * @param Request $request
     * @param Contact $contact
     * @param Activity $activity
     * @return \Illuminate\Http\Response
     */
    public function destroy(Request $request, Contact $contact, $activityId)
    {
        $data = [
            'account_id' => auth()->user()->account->id,
            'activity_id' => $activityId,
        ];

        try {
            (new DestroyActivity)->execute($data);
        } catch (\Exception $e) {
            return $this->respondNotFound();
        }
    }

    /**
     * Get the list of activity categories.
     *
     * @param Request $request
     * @return \Illuminate\Http\Response
     */
    public function categories(Request $request)
    {
        $categories = auth()->user()->account->activityTypeCategories;

        $array = collect([]);
        foreach ($categories as $category) {
            $types = ActivityType::where('activity_type_category_id', $category->id)->get();

            $typeCollection = collect([]);
            foreach ($types as $type) {
                $typeCollection->push([
                    'id' => $type->id,
                    'name' => $type->name,
                ]);
            }

            $array->push([
                'id' => $category->id,
                'name' => $category->name,
                'types' => $typeCollection,
            ]);
        }

        return $array;
    }

    /**
     * Get all the activities for this contact for a specific year.
     */
    public function year(Request $request, Contact $contact, int $year)
    {
        $startDate = Carbon::create($year, 1, 1);
        $endDate = Carbon::create($year, 12, 31);

        $activitiesLastTwelveMonths = $this->activityStatisticService
                        ->activitiesWithContactInTimeRange($contact, Carbon::now()->subMonths(12), Carbon::now())
                        ->count();

        $uniqueActivityTypes = $this->activityStatisticService
                        ->uniqueActivityTypesInTimeRange($contact, $startDate, $endDate);

        $activitiesPerYear = $this->activityStatisticService->activitiesPerYearWithContact($contact);

        $activitiesPerMonthForYear = $this->activityStatisticService
                        ->activitiesPerMonthForYear($contact, $year)
                        ->sortByDesc('month');

        return view('people.activities.index')
            ->withTotalActivities($contact->activities->count())
            ->withActivitiesLastTwelveMonths($activitiesLastTwelveMonths)
            ->withUniqueActivityTypes($uniqueActivityTypes)
            ->withActivitiesPerYear($activitiesPerYear)
            ->withActivitiesPerMonthForYear($activitiesPerMonthForYear)
            ->withYear($year)
            ->withContact($contact);
    }
}
