<?php $this->tpl('header') ?>
<style>
    body {
        background: url("<?=\view::src('img/sign-weixin.jpg')?>") no-repeat center center fixed;
        background-size: cover;
    }

    .frame {
        display: flex;
        justify-content: center;
        align-items: center;
        flex-direction: column;
    }

    .title {
        padding: 10rem 0 3rem;
    }

    .extra, .extra a {
        color: #fff;
        text-align: center;
    }
    .extra > div {
        padding: 0.5rem;
    }

    form, .form{
        display: flex;
        justify-content: center;
        align-items: center;
        align-content: center;
        flex-direction: column;
    }

    input, button.input {
        text-align: center;
        padding: 0.3rem;
        margin: 0.5rem;
        font-size: 1.5rem;
        width: 100%;
    }
</style>

<div class="frame">
    <div class="title">
        <img class="img" src="<?= \view::src('img/logo-head.png') ?>" alt="">
    </div>
    <div class="form">
        <input title="email" placeholder="请输入邮箱账号" name="account" type="email" id="email">
        <button class="input" id="btn-send">发送重置邮件</button>
    </div>
    <div class="extra">
        <div><span id="hint"></span></div>
        <div>
            <a href="/sign-in-email">返回登录页面</a>
        </div>
    </div>
</div>

<?=\view::js('resource/jquery/jquery.min')?>

<script type="text/javascript">
        $('#btn-send').click(function(){
            var hint = $('#hint');
            hint.html('');
            var the = $(this);
            the.attr('disabled', 'disabled');
            $.post('/sign-email-forget', {email: $('#email').val()}, function(res){
                the.attr('disabled', null);
                if (res.error === '0') {
                    hint.html('已成功发送，请通过邮件中的重置链接重置密码');
                } else {
                    hint.html(res.message);
                }
            })
        });
</script>

<?php $this->tpl('footer') ?>

