<?php

namespace App\Http\Controllers;

use App\Questions;
use App\QuestionsOptions;
use App\Surveys;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Webpatser\Uuid\Uuid;

class QuestionController extends Controller
{
    /**
     * Validate the question.
     */
    public function validateQuestion(Request $request)
    {
        Validator::extend('valid_question_options', function ($attribute, $value, $parameters, $validator) {
            return is_array($value) && (count($value) > 1 || array_search('free', array_column($value, 'type')) !== false);
        });
        $this->validate($request, [
            'description'               => 'required|max:1023|min:4',
            'questions_options'         => 'required|array|valid_question_options',
            'questions_options.*.value' => 'required|distinct|min:1|max:1023',
            'questions_options.*.type'  => 'in:check,free',
        ]);
    }

    /**
     * Show the question creation page.
     *
     * @return \Illuminate\Http\Response
     */
    public function create($uuid, Request $request)
    {
        $survey = Surveys::where('user_id', '=', $request->user()->id)->where('uuid', '=', $uuid)->limit(1)->get();

        if (count($survey) !== 1) {
            $request->session()->flash('warning', 'Survey "'.$uuid.'" not found.');

            return redirect()->route('dashboard');
        }

        return view('question.create')->with([
            'survey' => $survey[0],
        ]);
    }

    /**
     * Create a new question.
     *
     * @return \Illuminate\Http\Response
     */
    public function store($uuid, Request $request)
    {
        if (Surveys::isRunning($uuid) === Surveys::ERR_IS_RUNNING_SURVEY_OK) {
            $request->session()->flash('warning', 'Survey "'.$uuid.'" cannot be updated because it is being run.');

            return redirect()->route('survey.edit', $uuid);
        }

        $this->validateQuestion($request);

        $survey = Surveys::getByOwner($uuid, $request->user()->id);
        if (!$survey) {
            $request->session()->flash('warning', 'Survey "'.$uuid.'" not found.');

            return redirect()->route('dashboard');
        }

        $question = Questions::createQuestion([
            'description' => $request->input('description'),
            'uuid'        => Uuid::generate(4),
            'survey_id'   => $survey->id,
            'order'       => Questions::getNextInOrder($survey->id),
        ]);

        $questions_options = $request->input('questions_options');

        Questions::createQuestionOptions($question, $questions_options);

        $request->session()->flash('success', 'Question '.$question->uuid.' successfully created!');

        return redirect()->route('survey.edit', $survey->uuid);
    }

    /**
     * Delete the question.
     *
     * @return \Illuminate\Http\Response
     */
    public function delete($s_uuid, $q_uuid, Request $request)
    {
        if (Surveys::isRunning($s_uuid) === Surveys::ERR_IS_RUNNING_SURVEY_OK) {
            $request->session()->flash('warning', 'Survey "'.$s_uuid.'" cannot be updated because it is being run.');

            return redirect()->route('survey.edit', $s_uuid);
        }

        if (Questions::deleteByOwner($s_uuid, $q_uuid, $request->user()->id)) {
            $request->session()->flash('success', 'Question "'.$q_uuid.'" successfully removed!');

            return redirect()->route('survey.edit', $s_uuid);
        }

        $request->session()->flash('warning', 'Question "'.$q_uuid.'" not found.');

        return redirect()->route('survey.edit', $s_uuid);
    }

    /**
     * Display the question's editing page.
     *
     * @return \Illuminate\Http\Response
     */
    public function edit($s_uuid, $q_uuid, Request $request)
    {
        $survey = Surveys::getByOwner($s_uuid, $request->user()->id);
        if (!$survey) {
            $request->session()->flash('warning', 'Survey "'.$s_uuid.'" not found.');

            return redirect()->route('dashboard');
        }

        if ($survey->is_running) {
            $request->session()->flash('warning', 'Survey "'.$s_uuid.'" is running.');

            return redirect()->route('survey.edit', $s_uuid);
        }

        $question = Questions::getBySurvey($q_uuid, $survey->id);
        if (!$question) {
            $request->session()->flash('warning', 'Question "'.$q_uuid.'" not found.');

            return redirect()->route('survey.edit', $s_uuid);
        }

        $question_options = QuestionsOptions::getAllByQuestionIdAsJSON($question->id);

        return view('question.edit')->with([
            'survey'           => $survey,
            'question'         => $question,
            'question_options' => $question_options,
        ]);
    }

    /**
     * Update the question.
     *
     * @return \Illuminate\Http\Response
     */
    public function update($s_uuid, $q_uuid, Request $request)
    {
        $this->validateQuestion($request);

        $survey = Surveys::getByOwner($s_uuid, $request->user()->id);
        if (!$survey) {
            $request->session()->flash('warning', 'Survey "'.$s_uuid.'" not found.');

            return redirect()->route('dashboard');
        }

        if ($survey->is_running) {
            $request->session()->flash('warning', 'Survey "'.$s_uuid.'" cannot be updated because it is running.');

            return redirect()->route('survey.edit', $s_uuid);
        }

        $question = Questions::getBySurvey($q_uuid, $survey->id);
        if (!$question) {
            $request->session()->flash('warning', 'Question "'.$q_uuid.'" not found.');

            return redirect()->route('survey.edit', $s_uuid);
        }

        $question->description = $request->input('description');
        $question->save();

        QuestionsOptions::saveArray($question->id, $request->input('questions_options'));

        $request->session()->flash('success', 'Question '.$question->uuid.' successfully updated!');

        return redirect()->route('survey.edit', $survey->uuid);
    }

    /**
     * Display the change questions' order page.
     *
     * @return \Illuminate\Http\Response
     */
    public function showChangeOrder($s_uuid, Request $request)
    {
        $survey = Surveys::getByOwner($s_uuid, $request->user()->id);
        if (!$survey) {
            $request->session()->flash('warning', 'Survey "'.$s_uuid.'" not found.');

            return redirect()->route('dashboard');
        }

        if ($survey->is_running) {
            $request->session()->flash('warning', 'Survey "'.$s_uuid.'" is running.');

            return redirect()->route('survey.edit', $s_uuid);
        }

        $questions = Questions::getAllBySurveyIdUnpaginated($survey->id);

        return view('question.change_order')->with([
            'survey'    => $survey,
            'questions' => $questions,
        ]);
    }

    /**
     * Update the questions' order.
     *
     * @return \Illuminate\Http\Response
     */
    public function storeChangeOrder($s_uuid, Request $request)
    {
        $survey = Surveys::getByOwner($s_uuid, $request->user()->id);
        if (!$survey) {
            $request->session()->flash('warning', 'Survey "'.$s_uuid.'" not found.');

            return redirect()->route('dashboard');
        }

        if ($survey->is_running) {
            $request->session()->flash('warning', 'Survey "'.$s_uuid.'" is running.');

            return redirect()->route('survey.edit', $s_uuid);
        }

        $questions = $request->input('questions');
        foreach ($questions as $order => $question) {
            Questions::updateOrder($question['id'], $order + 1);
        }

        $request->session()->flash('success', 'Questions order updated!');

        return redirect()->route('survey.edit', $s_uuid);
    }
}
