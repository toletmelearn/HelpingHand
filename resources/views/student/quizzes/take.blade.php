@extends('layouts.student')

@section('content')
<div class="container py-4">
    <div class="row">
        <div class="col-md-8 mb-4">
            <h1 class="h3 mb-2 text-gray-800">{{ $quiz->title }}</h1>
            <p class="mb-4">Read each question carefully and select the correct option before the timer runs out.</p>
        </div>
        <div class="col-md-4 mb-4 text-end d-flex align-items-center justify-content-end">
            <div class="alert alert-info py-2 px-3 mb-0 text-center font-weight-bold" style="min-width: 150px;">
                <span id="timer">{{ $quiz->duration_minutes }}:00</span> Mins Left
            </div>
        </div>
    </div>

    <div class="row">
        <div class="col-md-12">
            <form id="quizForm" action="{{ route('student.quizzes.submit', $quiz->id) }}" method="POST">
                @csrf
                @foreach($quiz->questions as $index => $question)
                    <div class="card shadow mb-4">
                        <div class="card-header py-3 bg-light">
                            <h6 class="m-0 font-weight-bold text-dark">Question {{ $index + 1 }}</h6>
                        </div>
                        <div class="card-body">
                            <p class="font-weight-bold mb-3">{{ $question->question_text }}</p>
                            
                            <div class="form-check mb-2">
                                <input class="form-check-input" type="radio" name="answers[{{ $question->id }}]" id="q{{ $question->id }}a" value="a" required>
                                <label class="form-check-label" for="q{{ $question->id }}a">
                                    A) {{ $question->option_a }}
                                </label>
                            </div>
                            
                            <div class="form-check mb-2">
                                <input class="form-check-input" type="radio" name="answers[{{ $question->id }}]" id="q{{ $question->id }}b" value="b">
                                <label class="form-check-label" for="q{{ $question->id }}b">
                                    B) {{ $question->option_b }}
                                </label>
                            </div>
                            
                            <div class="form-check mb-2">
                                <input class="form-check-input" type="radio" name="answers[{{ $question->id }}]" id="q{{ $question->id }}c" value="c">
                                <label class="form-check-label" for="q{{ $question->id }}c">
                                    C) {{ $question->option_c }}
                                </label>
                            </div>
                            
                            <div class="form-check mb-2">
                                <input class="form-check-input" type="radio" name="answers[{{ $question->id }}]" id="q{{ $question->id }}d" value="d">
                                <label class="form-check-label" for="q{{ $question->id }}d">
                                    D) {{ $question->option_d }}
                                </label>
                            </div>
                        </div>
                    </div>
                @endforeach

                <div class="text-center mb-5">
                    <button type="submit" class="btn btn-success btn-lg px-5">Submit Quiz Answers</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
    document.addEventListener("DOMContentLoaded", function () {
        var duration = {{ $quiz->duration_minutes }} * 60;
        var display = document.querySelector('#timer');
        
        var timer = duration, minutes, seconds;
        var interval = setInterval(function () {
            minutes = parseInt(timer / 60, 10);
            seconds = parseInt(timer % 60, 10);

            minutes = minutes < 10 ? "0" + minutes : minutes;
            seconds = seconds < 10 ? "0" + seconds : seconds;

            display.textContent = minutes + ":" + seconds;

            if (--timer < 0) {
                clearInterval(interval);
                alert("Time is up! Submitting answers automatically.");
                document.getElementById('quizForm').submit();
            }
        }, 1000);
    });
</script>
@endsection
