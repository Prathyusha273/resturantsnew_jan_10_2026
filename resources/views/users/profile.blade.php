@extends('layouts.app')
@section('content')
<?php
$countries = file_get_contents(public_path('countriesdata.json'));
$countries = json_decode($countries);
$countries = (array) $countries;
$newcountries = array();
$newcountriesjs = array();
foreach ($countries as $keycountry => $valuecountry) {
    $newcountries[$valuecountry->phoneCode] = $valuecountry;
    $newcountriesjs[$valuecountry->phoneCode] = $valuecountry->code;
}
?>
    <div class="page-wrapper">
        <div class="row page-titles">
            <div class="col-md-5 align-self-center">
                <h3 class="text-themecolor">{{trans('lang.user_profile')}}</h3>
            </div>
            <div class="col-md-7 align-self-center">
                <ol class="breadcrumb">
                    <li class="breadcrumb-item"><a href="{!! route('home') !!}">{{trans('lang.dashboard')}}</a>
                    </li>
                    <li class="breadcrumb-item active">{{trans('lang.user_profile_edit')}}</li>
                </ol>
            </div>
        </div>
        <div class="container-fluid">
            <div class="row">
                <div class="col-12">
                    <div class="resttab-sec">
                        <div class="error_top"></div>
                        <div class="row restaurant_payout_create">
                            <div class="restaurant_payout_create-inner">
                                <fieldset>
                                    <legend>{{trans('lang.basic_details')}}</legend>
                                    <div class="form-group row width-50">
                                        <label class="col-3 control-label">{{trans('lang.first_name')}}</label>
                                        <div class="col-7">
                                            <input type="text" class="form-control user_first_name" required>
                                            <div class="form-text text-muted">
                                                {{ trans("lang.user_first_name_help") }}
                                            </div>
                                        </div>
                                    </div>
                                    <div class="form-group row width-50">
                                        <label class="col-3 control-label">{{trans('lang.last_name')}}</label>
                                        <div class="col-7">
                                            <input type="text" class="form-control user_last_name">
                                            <div class="form-text text-muted">
                                                {{ trans("lang.user_last_name_help") }}
                                            </div>
                                        </div>
                                    </div>
                                    <div class="form-group row width-50">
                                        <label class="col-3 control-label">{{trans('lang.email')}}</label>
                                        <div class="col-7">
                                            <input type="email" class="form-control user_email" disabled required>
                                            <div class="form-text text-muted">
                                                {{ trans("lang.user_email_help") }}
                                            </div>
                                        </div>
                                    </div>
                                    <div class="form-group form-material" >
                                            <label class="col-3 control-label">{{trans('lang.user_phone')}}</label>
                                            <div class="col-12">
                                                <div class="phone-box position-relative" id="phone-box">
                                                    <select name="country" id="country_selector" disabled>
                                                        <?php foreach ($newcountries as $keycy => $valuecy) { ?>
                                                        <?php    $selected = ""; ?>
                                                        <option <?php    echo $selected; ?> code="<?php    echo $valuecy->code; ?>"
                                                                value="<?php    echo $keycy; ?>">
                                                            +<?php    echo $valuecy->phoneCode; ?> {{$valuecy->countryName}}</option>
                                                        <?php } ?>
                                                    </select>
                                                    <input class="form-control user_phone" disabled placeholder="Phone" id="phone" type="phone"
                                                            name="phone" value="{{ old('phone') }}" required
                                                        autocomplete="phone" autofocus>
                                                    <div id="error2" class="err"></div>
                                                </div>
                                            </div>
                                    </div>
                                    <div class="form-group row">
                                        <label class="col-3 control-label">{{trans('lang.profile_pic')}}</label>
                                        <div class="col-9">
                                            <input type="file" onChange="handleFileSelectowner(event,'vendor')">
                                            <div id="uploding_image_owner"></div>
                                            <div class="uploaded_image_owner mt-3" style="display:none;"><img
                                                        id="uploaded_image_owner" src="" width="150px" height="150px;">
                                            </div>
                                            <div class="form-text text-muted">
                                                {{ trans("lang.restaurant_image_help") }}
                                            </div>
                                        </div>
                                    </div>
                                </fieldset>
                                <fieldset class="password_div" >
                                    <legend>{{trans('lang.password')}}</legend>
                                    <div class="form-group row width-50">
                                        <label class="col-3 control-label">{{trans('lang.old_password')}}</label>
                                        <div class="col-7">
                                            <input type="password" class="form-control user_old_password" required>
                                            <div class="form-text text-muted">
                                                {{ trans("lang.user_password_help") }}
                                            </div>
                                        </div>
                                    </div>
                                    <div class="form-group row width-50">
                                        <label class="col-3 control-label">{{trans('lang.new_password')}}</label>
                                        <div class="col-7">
                                            <input type="password" class="form-control user_new_password" required>
                                            <div class="form-text text-muted">
                                                {{ trans("lang.user_password_help") }}
                                            </div>
                                        </div>
                                    </div>
                                    <div class="form-group col-12 text-center">
                                        <button type="button" class="btn btn-primary  change_user_password"><i
                                                    class="fa fa-save"></i>{{trans('lang.change_password')}}</button>
                                    </div>
                                </fieldset>
                                <fieldset>
                                    <legend>{{trans('lang.bankdetails')}}</legend>
                                    <div class="form-group row">
                                                <div class="form-group row width-100">
                                                    <label class="col-4 control-label">{{trans('lang.bank_name')}}</label>
                                                    <div class="col-7">
                                                        <input type="text" name="bank_name" class="form-control" id="bankName">
                                                    </div>
                                                </div>
                                                <div class="form-group row width-100">
                                                    <label class="col-4 control-label">{{trans('lang.branch_name')}}</label>
                                                    <div class="col-7">
                                                        <input type="text" name="branch_name" class="form-control"
                                                            id="branchName">
                                                    </div>
                                                </div>
                                                <div class="form-group row width-100">
                                                    <label class="col-4 control-label">{{trans('lang.holer_name')}}</label>
                                                    <div class="col-7">
                                                        <input type="text" name="holer_name" class="form-control"
                                                            id="holderName">
                                                    </div>
                                                </div>
                                                <div class="form-group row width-100">
                                                    <label class="col-4 control-label">{{trans('lang.account_number')}}</label>
                                                    <div class="col-7">
                                                        <input type="text" name="account_number" class="form-control"
                                                            id="accountNumber">
                                                    </div>
                                                </div>
                                                <div class="form-group row width-100">
                                                    <label class="col-4 control-label">{{trans('lang.other_information')}}</label>
                                                    <div class="col-7">
                                                        <input type="text" name="other_information" class="form-control"
                                                            id="otherDetails">
                                                    </div>
                                                </div>
                                    </div>
                                </fieldset>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="form-group col-12 text-center btm-btn">
                <button type="button" class="btn btn-primary  save_restaurant_btn"><i
                            class="fa fa-save"></i> {{trans('lang.save')}}</button>
                <a href="{!! route('home') !!}" class="btn btn-default"><i
                            class="fa fa-undo"></i>{{trans('lang.cancel')}}</a>
            </div>
        </div>
    </div>
    </div>
@endsection
@section('scripts')
    <script src="https://cdnjs.cloudflare.com/ajax/libs/compressorjs/1.1.1/compressor.min.js"
            integrity="sha512-VaRptAfSxXFAv+vx33XixtIVT9A/9unb1Q8fp63y1ljF+Sbka+eMJWoDAArdm7jOYuLQHVx5v60TQ+t3EA8weA=="
            crossorigin="anonymous" referrerpolicy="no-referrer"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/moment.js/2.26.0/moment.min.js"></script>
    <script>
        $(document).ready(function () {
            let user = @json($user);
            let placeholderImage = "{{ $placeholderImage }}";

            $(".user_first_name").val(user.firstName);
            $(".user_last_name").val(user.lastName);
            $(".user_email").val(user.email);
            $(".user_phone").val(user.phoneNumber);

            if (user.profilePictureURL) {
                $("#uploaded_image_owner").attr('src', user.profilePictureURL);
            } else {
                $("#uploaded_image_owner").attr('src', placeholderImage);
            }
            $(".uploaded_image_owner").show();
        });

        // Handle photo preview
        function handleFileSelectowner(evt) {
            var f = evt.target.files[0];
            var reader = new FileReader();
            reader.onload = function (e) {
                $("#uploaded_image_owner").attr('src', e.target.result);
                $(".uploaded_image_owner").show();
            };
            reader.readAsDataURL(f);
        }

        // Save via AJAX
        $(".save_restaurant_btn").click(function () {

            let formData = new FormData();
            formData.append("first_name", $(".user_first_name").val());
            formData.append("last_name", $(".user_last_name").val());
            formData.append("phone", $(".user_phone").val());

            if ($("input[type=file]")[0].files[0]) {
                formData.append("photo", $("input[type=file]")[0].files[0]);
            }

            formData.append("bank_name", $("#bankName").val());
            formData.append("branch_name", $("#branchName").val());
            formData.append("holder_name", $("#holderName").val());
            formData.append("account_number", $("#accountNumber").val());
            formData.append("other_information", $("#otherDetails").val());

            formData.append("_token", "{{ csrf_token() }}");

            $.ajax({
                url: "{{ route('user.profile.update') }}",
                method: "POST",
                data: formData,
                cache: false,
                processData: false,
                contentType: false,
                success: function (res) {
                    alert("Profile updated successfully!");
                    location.reload();
                },
                error: function (xhr) {
                    alert("Something went wrong");
                }
            });
        });
    </script>
@endsection
