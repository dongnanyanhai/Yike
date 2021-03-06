<?php


namespace _;


use PHPUnit\Framework\TestCase;

class payoffTest extends TestCase
{
    private $platform;
    private $mysql;
    private $redis;

    private $users;

    public function __construct($name = null, array $data = array(), $dataName = '')
    {
        parent::__construct($name, $data, $dataName);
        $this->mysql = data::mysql();
        $this->redis = data::redis();
    }

    public function setUp()
    {
        parent::setUp(); // TODO: Change the autogenerated stub
        // user
        $users = [
            [ 'id' => 1, 'sn' => 'Ut', 'name' => 'Teacher' ],
            [ 'id' => 2, 'sn' => 'Us', 'name' => 'Student' ],
            [ 'id' => 3, 'sn' => 'Up', 'name' => 'Promote' ],
            [ 'id' => 4, 'sn' => 'Uc', 'name' => 'Channel' ],
            [ 'id' => 5, 'sn' => 'Uf', 'name' => 'Friends' ],
        ];
        $this->users = array_column($users, 'id', 'sn');
        $this->users['_'] = 0; // 平台uid=0
        foreach ($users as $user) {
            $this->mysql->insert('user', $user);
        }
        // lesson
        $this->mysql->insert('lesson', [
            'id' => 1,
            'sn' => 'Lv1',
            'tuid' => 1, //
            'title' => 'View1',
            'i_form' => dataLesson::FORM_VIEW,
            'price' => 10000,
            'i_step' => dataLesson::STEP_FINISH,
            'extra' => json_encode([ // 兼容旧配置，未显示申明关闭平台分销的，默认30%分享奖惩，49.5%讲师分成
                'cover' => '',
                'conf' => []], JSON_FORCE_OBJECT)
        ]);
        // article
        $this->mysql->insert('lesson', [
            'id' => 2,
            'sn' => 'La1',
            'tuid' => 1, //
            'title' => 'Article1',
            'i_form' => dataLesson::FORM_ARTICLE,
            'price' => 10000,
            'i_step' => dataLesson::STEP_FINISH,
            'extra' => json_encode([ // 讲师配置Uc渠道佣金1500, 平台分成4900，分享奖励默认30%，余下归讲师（未授权平台推广）
                'cover' => '',
                'conf' => [
                    'sharing' => [
                        'Uc' => 0.15
                    ]
                ]
            ], JSON_FORCE_OBJECT)
        ]);
        // column
        $this->mysql->insert('lesson', [
            'id' => 3,
            'sn' => 'Lc1',
            'tuid' => 1, //
            'title' => 'Column1',
            'i_form' => dataLesson::FORM_ARTICLE,
            'price' => 10000,
            'i_step' => dataLesson::STEP_FINISH,
            'extra' => json_encode([ // 讲师授权平台推广，讲师保留selling._，平台渠道Uc可分20，未定义渠道不予分成
                'cover' => '',
                'conf' => [
                    'sharing' => [
                        '_' => true
                    ],
                    'selling' => [
                        '_' => 0.495,
                        'Uc' => 0.2,
                    ]
                ]
            ], JSON_FORCE_OBJECT)
        ]);
        // column
        $this->mysql->insert('lesson', [
            'id' => 4,
            'sn' => 'Lc2',
            'tuid' => 1,
            'title' => 'Column1',
            'i_form' => dataLesson::FORM_ARTICLE,
            'price' => 10000,
            'i_step' => dataLesson::STEP_FINISH,
            'extra' => json_encode([ // 讲师授权平台推广，讲师分成49.5，未单独配置的渠道可分的selling.*
                'cover' => '',
                'conf' => [
                    'sharing' => [
                        '_' => true
                    ],
                    'selling' => [
                        '_' => 0.495,
                        '*' => 0.2,
                        'Uc' => 0.3,
                    ]
                ]
            ], JSON_FORCE_OBJECT)
        ]);
        // 分享奖励+渠道分成超过100%的异常情况
        $this->mysql->insert('lesson', [
            'id' => 5,
            'sn' => 'La2',
            'tuid' => 1,
            'title' => 'Article2',
            'i_form' => dataLesson::FORM_ARTICLE,
            'price' => 10000,
            'i_step' => dataLesson::STEP_FINISH,
            'extra' => json_encode([
                'cover' => '',
                'conf' => [ // 配置的渠道分成超限
                    'commission' => 0.5,
                    'sharing' => [
                        'Uc' => 0.8
                    ]
                ]
            ])
        ]);
        // 设置了购买抵扣
        $this->mysql->insert('lesson', [
            'id' => 6,
            'sn' => 'La3',
            'tuid' => 1,
            'title' => 'Article3',
            'i_form' => dataLesson::FORM_ARTICLE,
            'price' => 10000,
            'i_step' => dataLesson::STEP_FINISH,
            'extra' => json_encode([
                'cover' => '',
                'conf' => [
                    'discount' => 0.1, // 10%优惠
                    'commission' => 0.2, // 20%佣金
                ]
            ])
        ]);
        // show me the money
        servMoney::sole($this->platform)->change(dataMoney::ITEM_REWARD, $this->users['Us'], 10000);
    }

    public function tearDown()
    {
//        sleep(10);
        // clear table
        $tables = ['user', 'dict_origin', 'order', 'payoff', 'money', 'lesson', 'lesson_series', 'lesson_promote'];
        foreach ($tables as $table) {
            $this->mysql->run("truncate table `$table`");
        }
        // clear promote
        $keys = $this->redis->keys("PROMOTE_DRAW*");
        if ($keys) {
            $this->redis->del(...$keys);
        }
        parent::tearDown(); // TODO: Change the autogenerated stub
    }


    /**
     * 购买单课
     * @dataProvider dataLessonScheme
     * @param $scheme
     */
    public function testLesson($scheme)
    {
        $originId = servOrigin::sole($this->platform)->checkIn($scheme['origin'] ?? '_');
        // 领券
        if (isset($scheme['promote'])) {
            $psn = servPromote::sole($this->platform)->generate($scheme['lesson'], $scheme['promote'], $scheme['origin']??null);
//            $pOrigindId = servOrigin::sole($this->platform)->checkIn("promote-$scheme[promote]");
            servPromote::sole($this->platform)->draw($psn, $this->users[$scheme['student']]);
        } else {
            $psn = 'none';
        }
        // 下单
        $enroll = \Student\servLesson::sole($this->platform)->enroll($scheme['lesson'], $this->users[$scheme['student']], $originId, null);
//        $order = dataOrder::sole($this->platform)->fetchOne(['sn' => $enroll['order']], ['origin_id']);
        // 支付
        servOrders::sole($this->platform)->purchase($enroll['order']);
        $orderId = dataOrder::sole($this->platform)->fetchOne(['sn' => $enroll['order']], 'id', 0);
        $payoff = dataPayoff::sole($this->platform)->fetchAll(['order_id'=>$orderId], ['uid', 'i_item', 'amount'], null, null, "order by uid, i_item");
        $expect = [];
        foreach ($scheme['payoff'] as $item) {
            $expect[] = [
                'uid' => $this->users[$item['usn']],
                'i_item' => $item['item'],
                'amount' => $item['amount']
            ];
        }
        $this->assertSame($expect, $payoff, "`$scheme[student]` purchase `$scheme[lesson]` from `$scheme[origin]` with promote `$psn``");
    }

    public function dataLessonScheme()
    {
        return [
            [[ // 缺少配置，默认分享奖励30%，讲师分成50%
                'lesson' => 'Lv1',
                'origin' => 'home-Uc',
                'promote' => 'Up',
                'student' => 'Us',
                'payoff' => [
                    ['usn' => '_', 'item' => dataMoney::ITEM_SERVICE_FEE, 'amount' => 100], // 平台服务费1
                    ['usn' => '_', 'item' => dataMoney::ITEM_LESSON_INCOME, 'amount' => 1950], // 平台分成
                    ['usn' => 'Ut', 'item' => dataMoney::ITEM_LESSON_INCOME, 'amount' => 4950], // 讲师分成(100-1)*0.5
                    ['usn' => 'Up', 'item' => dataMoney::ITEM_COMMISSION, 'amount' => 3000], // 邀请奖励30
                ]
            ]],
            [[ // 讲师配置第三方渠道，平台收取1%服务费，分享奖励默认30%，渠道佣金15，剩余归讲师
                'lesson' => 'La1',
                'origin' => 'home-Uc',
                'student' => 'Us',
                'promote' => 'Up',
                'payoff' => [
                    ['usn' => '_', 'item' => dataMoney::ITEM_SERVICE_FEE, 'amount' => 100], // 平台服务费1
                    ['usn' => 'Ut', 'item' => dataMoney::ITEM_LESSON_INCOME, 'amount' => 5400], // 讲师分成
                    ['usn' => 'Up', 'item' => dataMoney::ITEM_COMMISSION, 'amount' => 3000], // 邀请奖励3000
                    ['usn' => 'Uc', 'item' => dataMoney::ITEM_COMMISSION, 'amount' => 1500], // 讲师渠道佣金1500
                ]
            ]],
            [[ // 讲师授权平台推广，平台渠道Uc按配置分成20，讲师分得配置的49.5，剩余归平台
                'lesson' => 'Lc1',
                'origin' => 'home-Uc',
                'student' => 'Us',
                'payoff' => [
                    ['usn' => '_', 'item' => dataMoney::ITEM_SERVICE_FEE, 'amount' => 100],
                    ['usn' => '_', 'item' => dataMoney::ITEM_LESSON_INCOME, 'amount' => 2950],
                    ['usn' => 'Ut', 'item' => dataMoney::ITEM_LESSON_INCOME, 'amount' => 4950], // 平台渠道讲师固定分成4950
                    ['usn' => 'Uc', 'item' => dataMoney::ITEM_COMMISSION, 'amount' => 2000], // 平台渠道佣金20
                ]
            ]],
            [[ // 平台推广，讲师分得配置的49.5，剩余归平台
                'lesson' => 'Lc1',
                'origin' => '_',
                'student' => 'Us',
                'payoff' => [
                    ['usn' => '_', 'item' => dataMoney::ITEM_SERVICE_FEE, 'amount' => 100],
                    ['usn' => '_', 'item' => dataMoney::ITEM_LESSON_INCOME, 'amount' => 4950],
                    ['usn' => 'Ut', 'item' => dataMoney::ITEM_LESSON_INCOME, 'amount' => 4950],
                ]
            ]],
            [[ // 来自未经单独配置的平台分销渠道
                'lesson' => 'Lc2',
                'origin' => 'home-Uf',
                'student' => 'Us',
                'payoff' => [
                    ['usn' => '_', 'item' => dataMoney::ITEM_SERVICE_FEE, 'amount' => 100],
                    ['usn' => '_', 'item' => dataMoney::ITEM_LESSON_INCOME, 'amount' => 2950],
                    ['usn' => 'Ut', 'item' => dataMoney::ITEM_LESSON_INCOME, 'amount' => 4950],
                    ['usn' => 'Uf', 'item' => dataMoney::ITEM_COMMISSION, 'amount' => 2000]
                ]
            ]],
            [[ // 优先分享奖励，渠道佣金分走剩下的，讲师没有分成
                'lesson' => 'La2',
                'origin' => 'home-Uc',
                'student' => 'Us',
                'promote' => 'Up',
                'payoff' => [
                    ['usn' => '_', 'item' => dataMoney::ITEM_SERVICE_FEE, 'amount' => 100],
                    ['usn' => 'Up', 'item' => dataMoney::ITEM_COMMISSION, 'amount' => 5000],
                    ['usn' => 'Uc', 'item' => dataMoney::ITEM_COMMISSION, 'amount' => 4900]
                ]
            ]],
            [[ // 同时有折扣和佣金
                'lesson' => 'La3',
                'origin' => 'promote-Up',
                'student' => 'Us',
                'promote' => 'Up',
                'payoff' => [
                    ['usn' => '_', 'item' => dataMoney::ITEM_SERVICE_FEE, 'amount' => 100],
                    ['usn' => '_', 'item' => dataMoney::ITEM_LESSON_INCOME, 'amount' => 1950],
                    ['usn' => 'Ut', 'item' => dataMoney::ITEM_LESSON_INCOME, 'amount' => 4950],
                    ['usn' => 'Up', 'item' => dataMoney::ITEM_COMMISSION, 'amount' => 2000],
                ]
            ]]
        ];
    }
}
