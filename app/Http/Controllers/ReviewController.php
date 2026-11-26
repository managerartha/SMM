<?php

namespace App\Http\Controllers;

use App\Domain\Approval\ApprovalMatrix;
use App\Domain\Reviews\ManagementReviewService;
use App\Models\ManagementReview;
use App\Models\ReviewAction;
use App\Models\User;
use Illuminate\Http\Request;

class ReviewController extends Controller
{
    public function __construct(private ManagementReviewService $service) {}

    public function index(Request $request)
    {
        abort_unless($request->user()->can('tinjauan.lihat'), 403);

        return view('reviews.index', ['items' => ManagementReview::withCount('actions')->orderByDesc('meeting_date')->get()]);
    }

    public function store(Request $request)
    {
        $data = $request->validate(['meeting_date' => ['required', 'date'], 'period_from' => ['required', 'date'], 'period_to' => ['required', 'date', 'after_or_equal:period_from'], 'location' => ['nullable', 'string', 'max:255'], 'attendees' => ['required', 'string', 'max:5000']], [], ['attendees' => 'peserta']);
        $mr = $this->service->create($request->user(), $data);

        return redirect()->route('reviews.show', $mr)->with('status', "{$mr->number} dibuat. Masukan sudah diisi otomatis dari data SMM – periksa dan lengkapi.");
    }

    public function show(Request $request, ManagementReview $review, ApprovalMatrix $matrix)
    {
        $user = $request->user();
        $review->load(['actions.pic', 'file']);
        abort_unless($user->can('tinjauan.lihat') || $review->actions->contains('pic_id', $user->id), 403);

        return view('reviews.show', ['m' => $review, 'users' => User::active()->orderBy('name')->get(), 'canApprove' => $review->status === 'pengesahan' && $matrix->canApprove($user, 'management_review_minutes', 'mutu.sahkan')]);
    }

    public function update(Request $request, ManagementReview $review)
    {
        $data = $request->validate(['location' => ['nullable', 'string', 'max:255'], 'attendees' => ['required', 'string'], 'inputs' => ['array'], 'inputs.*' => ['nullable', 'string', 'max:10000'], 'outputs' => ['array'], 'outputs.*' => ['nullable', 'string', 'max:10000']]);
        $this->service->update($request->user(), $review, $data);

        return back()->with('status', 'Notulen disimpan.');
    }

    public function refresh(Request $request, ManagementReview $review)
    {
        $this->service->refreshInputs($request->user(), $review);

        return back()->with('status', 'Masukan diisi ulang dari data SMM terbaru.');
    }

    public function addAction(Request $request, ManagementReview $review)
    {
        $data = $request->validate(['description' => ['required', 'string', 'max:2000'], 'pic_id' => ['required', 'integer', 'exists:users,id'], 'due_date' => ['required', 'date']]);
        $this->service->addAction($request->user(), $review, $data);

        return back()->with('status', 'Tindak lanjut ditambahkan.');
    }

    public function completeAction(Request $request, ManagementReview $review, ReviewAction $action)
    {
        abort_unless($action->management_review_id === $review->id, 404);
        $data = $request->validate(['result' => ['required', 'string', 'max:5000']]);
        $this->service->completeAction($request->user(), $action, $data['result']);

        return back()->with('status', 'Tindak lanjut ditandai selesai.');
    }

    public function submit(Request $request, ManagementReview $review)
    {
        $this->service->submit($request->user(), $review);

        return back()->with('status', 'Notulen diajukan untuk pengesahan.');
    }

    public function decide(Request $request, ManagementReview $review)
    {
        $d = $request->validate(['decision' => ['required', 'in:sahkan,tolak'], 'note' => ['nullable', 'string', 'max:2000']]);
        $this->service->decide($request->user(), $review, $d['decision'] === 'sahkan', $d['note'] ?? null);

        return back()->with('status', $d['decision'] === 'sahkan' ? 'Notulen disahkan.' : 'Notulen dikembalikan.');
    }
}
