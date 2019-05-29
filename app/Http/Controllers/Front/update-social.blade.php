@extends('layouts.frontend.template')
<!-- css -->
<link rel="stylesheet" href="{{asset('css/frontend/account.css')}}?=0.0.1">
<link rel="stylesheet" href="{{asset('css/frontend-mobile/account-mobile.css')}}">
<script src="https://unpkg.com/sweetalert/dist/sweetalert.min.js"></script>

@section('content')
<section id="account">
    <div class="container">
        <div class="row">
            <div class="col-md-12 text-center">
                <!---------------------------- PROFILE ---------------------------->
                <div class="row profile-list">
                    <div class="col-md-12 d-flex justify-content-center">
                        <div class="card card-profile">
                            <div class="card-header">
                                <div class="row">
                                    <div class="col-md-12">
                                        <p class="head-profile">บัญชีของฉัน</p>
                                    </div>
                                </div>
                            </div>
                            <form method="post" id="update-social" enctype="multipart/form-data">
                                {{ csrf_field() }}
                                <div class="card-body">
                                    <div class="row">
                                        <div class="col-md-12">
                                            <div class="form-group">
                                                <div class="input-group">
                                                    <div class="input-group-prepend">
                                                        <div class="input-group-text"><i class="fas fa-user-circle"></i></div>
                                                    </div>
                                                <input type="text" class="form-control form-account" id="inlineFormInputGroupUsername" placeholder="ชื่อ-นามสกุล" 
                                                    value="{{$name}}" name="name" required>
                                                    <p class="text-danger" id="error-socail-name"></p>
                                                </div>
                                            </div>
                                            <div class="form-group">
                                                <div class="input-group">
                                                    <div class="input-group-prepend">
                                                        <div class="input-group-text"><i class="fas fa-envelope"></i></div>
                                                    </div>
                                                    <input type="email" class="form-control form-account" id="exampleInputEmail1" aria-describedby="emailHelp" placeholder="อีเมล์"
                                                        value="{{$email}}" name="email" required>
                                                </div>
                                                <p class="text-danger" id="error-socail-email"></p>
                                            </div>
                                            <div class="form-group">
                                                    <textarea class="form-control" name="address_1" placeholder="ที่อยู่" required></textarea>
                                            </div>
                                            <div class="row">
                                                <div class="col-6 col-md-6">
                                                <input type="text" class="form-control"  value="" name="district" id="district" placeholder="ตำบล" required>
                                                </div>
                                                <div class="col-6 col-md-6">    
                                                    <input type="text" class="form-control"  value="" id="amphoe"  name="amphoe" placeholder="อำเภอ" required> 
                                                    <p class="text-danger" id="error-zipcode"></p>
                                                </div>
                                            </div>
                                            <div class="row">
                                                <div class="col-6 col-md-6">
                                                <input type="text" class="form-control"  value="" name="zipcode" id="zipcode" placeholder="รหัสไปรษณีย์" required>
                                                <p class="text-danger" id="error-zipcode"></p>
                                                </div>
                                                <div class="col-6 col-md-6">    
                                                    <input type="text" class="form-control"  value="" id="province"  name="province" placeholder="จังหวัด" required>
                                                    <p class="text-danger" id="error-zipcode"></p>
                                                </div>
                                            </div>
                                            <div class="form-group">
                                                    <div class="input-group">
                                                        <div class="input-group-prepend">
                                                            <div class="input-group-text"><i class="fas fa-phone"></i></div>
                                                        </div>
                                                    <input type="tel" class="form-control form-account" id="phone-sms" placeholder="เบอร์โทรศัพท์ หรือ เบอร์มือถือ" value="" name="phone"  maxlength="10" required>
                                                    <button type="button" class="btn btn-sendphone" id="send-sms">ส่ง SMS ยืนยันตัวตน</button>
                                                    </div>
                                                    <p class="text-danger" id="error-socail-phone"></p>
                                                </div>
                                                <div class="form-group">
                                                    <input type="text" class="form-control" name="verify" maxlength="6" placeholder="รหัสยืนยันตัวตน 6 หลัก" required>
                                                    <p class="text-danger" id="error-verify"></p>
                                                </div>
                                            <button type="submit" class="btn btn-save">บันทึก</button>
                                        </div>
                                    </div>
                            </form>
                            </div>
                        </div>
                    </div>
                </div>
                <!---------------------------- END PROFILE ---------------------------->
            </div>
</section>
@endsection
 
@section('js')
<script type="text/javascript">
    $(document).ready(function(){

    $('body').on('submit', '#update-social', function(e) {
            e.preventDefault()
            $.ajax({
                url: '{{route('update.social')}}',
                cache: false,
                method : 'POST',
                data : $('#update-social').serialize(),
                success: function(response){
                    console.log(response.status)
                    let status = response.status ; 
                    if (status == false) {
                        console.log(response.responseJSON.status.error)
                    } else {
                        window.location.href = '/accounts'
                    }
                },
                error : function(error){
                    console.log(error.responseJSON);
                    let errors = error.responseJSON.errors;
                    let email = typeof(errors.email) == "undefined"  ?  '' : errors.email ;
                    let phone = typeof(errors.phone) == "undefined"  ?  '' : errors.phone ;
                    let name = typeof(errors.name) == "undefined"  ?  '' : errors.name ;
                    let verify = typeof(errors.verify) == "undefined"  ?  '' : errors.verify ;
                    $('#error-socail-name').html(name);
                    $('#error-socail-email').html(email);
                    $('#error-socail-phone').html(phone);
                    $('#error-verify').html(verify);
                }
            });  
        })
});
</script>
@endsection