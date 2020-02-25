<?php $data = $this->detail; ?>
<?= \view::js('markdown-it.min'); ?>
<?= \view::css('github-markdown'); ?>

<div class="portlet box blue">
    <div class="portlet-title">
        <div class="caption">
            <div class="caption">课程修改审核</div>
        </div>
    </div>
    <div class="portlet-body form portlet-empty">
        <form role="form" class="form-horizontal">
            <div class="form-body">
                <div class="form-group">
                    <label class="col-md-2 control-label">课程SN</label>
                    <div class="col-md-5">
                        <span class="form-control"><?=$data['lesson']['sn']?></span>
                    </div>
                </div>
                <div class="form-group">
                    <label class="col-md-2 control-label">讲师姓名</label>
                    <div class="col-md-5">
                        <span class="form-control"><?=$data['lesson']['teacher']['name']?></span>
                    </div>
                </div>
                <div class="form-group">
                    <label class="col-md-2 control-label">课程标题</label>
                    <div class="col-md-5">
                        <div class="form-control">
                            <?=$data['content']['title']?>
                        </div>
                    </div>
                </div>
                <div class="form-group">
                    <label class="col-md-2 control-label">公开展示</label>
                    <div class="col-md-5">
                        <div class="form-control">
                            <?= $data['content']['isPublic'] ?'是' :'否' ?>
                        </div>
                    </div>
                </div>
                <div class="form-group">
                    <label class="col-md-2 control-label">课程分类</label>
                    <div class="col-md-5">
                        <div class="form-control">
                            <a href="/lesson/series-detail?series_sn=<?=$data['lesson']['categoryInfo']['sn']?>"><?= $data['lesson']['categoryInfo']['name'] ?></a>
                        </div>
                    </div>
                </div>
                <div class="form-group">
                    <label class="col-md-2 control-label">课程封面</label>
                    <div class="col-md-5">
                       <img src="<?=\view::upload($data['content']['extra']['cover']) . '?imageView2/0/w/400'?>"/>
                    </div>
                </div>
                <div class="form-group">
                    <label class="col-md-2 control-label">课程简介</label>
                    <div class="col-md-5">
<!--                        <textarea class="form-control" rows="5" readonly>--><?//= $data['content']['brief'] ?><!--</textarea>-->
                        <div  id="introduce-content" class="markdown-body" style="border: 1px solid #e5e5e5;padding: 10px"></div>
                    </div>
                </div>
                <div class="form-group">
                    <label class="col-md-2 control-label">课程价格</label>
                    <div class="col-md-5">
                        <div class="form-control">
                            ￥ <?=$data['content']['price']?>
                        </div>
                    </div>
                </div>
                <div class="form-group">
                    <label class="col-md-2 control-label">开课时间</label>
                    <div class="col-md-5">
                        <div class="form-control">
                            <?=$data['content']['plan']['dtm_start']?>
                        </div>
                    </div>
                </div>
                <div class="form-group">
                    <label class="col-md-2 control-label">预计课时</label>
                    <div class="col-md-5">
                        <div class="form-control">
                            <?=$data['content']['plan']['duration']?>
                        </div>
                    </div>
                </div>
            </div>
            <div class="form-actions ">
                <div class="form-group">
                    <label class="col-md-2 control-label" for="remark">备注</label>
                    <div class="col-md-5">
                        <input id='remark' type="text" class="form-control" name="remark" placeholder="拒绝请填写理由"/>
                    </div>
                </div>
                <div class="form-group">
                    <label class="col-md-2 control-label" for="remark">发送微信通知</label>
                    <div class="col-md-5">
                        <input type="checkbox" id="sendMsg" name="sendMsg" value="" >向学员
                    </div>
                </div>
                <div class="form-group">
                    <label class="col-md-2 control-label" for="remark">变更内容</label>
                    <div class="col-md-5">
                        <input id='changeText' type="text" class="form-control" name="changeText" placeholder="通过并发送变更内容请填写"/>
                    </div>
                </div>
                <div class="row">
                    <div class="col-md-offset-2">

                        <button type="button" class="btn green" id="act-pass">通过</button>
                        <button type="button" class="btn red" id="act-deny">拒绝</button>
                    </div>
                </div>
            </div>
        </form>
        <script>
            jQuery(document).ready(function () {
                $('#act-pass').click(function(){
                    $.post('./lesson-pass', {
                        ticketId: '<?=$this->detail['id']?>',
                        sendMsg:document.getElementById('sendMsg').checked,
                        changeText:$("#changeText").val()
                    }, function(){
                        location.reload()
                    })
                });
                $('#act-deny').click(function(){
                    $.post('./lesson-deny', {
                        ticketId: '<?=$this->detail['id']?>',
                        remark: $("#remark").val()
                    }, function () {
                        location.reload()
                    })
                })
            });

            var md = window.markdownit();
            <?php $content = str_replace(["\n","\r\n","\r"], '\n', $data['content']['brief']);
            $content = str_replace("'","\'",$content);
            ?>
            var content = '<?=$content?>';
            var rst = md.render(content);

            ///////////////
            // 解析 table
            ///////////////
            var tcode = new RegExp(/(\|(?:[^\r\n\|]+\|)+)(?:\r?\n|\r)\|(?:[-—]+\|)+((?:(?:\r?\n|\r)(?:\|(?:[^\n\r\|]+\|)+))+)/,'gu'), curT = 1;
            while(curT !== null){
                curT=tcode.exec(rst);
                if(curT !== null){
                    console.log(curT[2].split(/\r?\n|\r/));
                    var rows = curT[2].split(/\r?\n|\r/).filter(function(a){return a.length === 0 ? false : true;}), rowtrs = '<table><thead><tr><td>'+curT[1].split('|').slice(1,-1).join('</td><td>')+'</td></tr></thead><tbody>';
                    console.log(rows);
                    for(var i in rows){
                        rowtrs += '<tr><td>'+rows[i].split('|').slice(1,-1).join("</td><td>")+'</td></tr>';
                    }
                    rowtrs += '</tbody></table>';
                    rst = rst.replace(curT[0], rowtrs);
                }
            }
            //            document.getElementById("introduce-content").innerHTML = rst;

            $('#introduce-content').html(rst);

        </script>
    </div>
</div>
