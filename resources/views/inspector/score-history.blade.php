@extends('master')

@section('content')
	<div class="content-wrapper ambient-key-shadows">
		<div class="row">
			<div class="col-sm-12">
				<h3 class="text-center">{{ trans('common.stats') }}
					<small>{{ $user_info->name . ' ' . $user_info->surname }}</small></h3>
				
				<form method="GET" class="form-inline">
					<input type="number" class="form-control" name="number" id="number" min="1" max="24" value="{{ $month_num }}">
					<button type="submit" class="btn btn-default">{{ trans('common.scorehistory_number_option_key') }}</button>
				</form>
				<div class="row">
					@foreach($score_history as $month=>$sh)
					<div class="col-sm-12 col-md-3">
						<div class="stat-item">
							<h3 class="text-center">
								{{ $month }}
							</h3>
							<p class="count text-center">
								<?php $sh_arr = explode(':', $sh);?>
								{{ number_format($sh_arr[0]) }} ({{ $sh_arr[1] }})
							</p>
						</div>
					</div>
					@endforeach
				</div>
			</div>
		</div>
	</div>

@endsection