<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use OpenAI\Laravel\Facades\OpenAI;

class TravelController extends Controller
{
    #入力値からgooglemapのルートを作成する
    public function generateTravelPlan(Request $request)
    {
        // TODOバリデーション

        $region = $request->input('region');
        $destinations = $request->input('destinations', []);

        // 候補地がない場合、自動で地域から一日で回れる地点を選定
        if (empty($destinations)) {
            $destinations = $this->generateDestinationsFromRegion($region);
            if (is_array($destinations) && empty($destinations)) {
                return response()->json(['error' => '候補地の生成に失敗しました。'], 500);
            }
        }

        // 候補地から緯度、経度を返すようにする
        
    }

    #候補地が2追加だったときに、openapiを使って新しい候補地を取得する
    private function generateDestinationsFromRegion($region)
    {
        // message内容
        $messages = [
            [
               'role' => 'user',
                'content' => "指定された地域: {$region} で一日で回れる人気の観光地を3つ提案してください。
                レスポンスは、以下のような形で返却してください。
                例（{$region}が東京だった場合）:
                [\"東京タワー\",\"浅草寺\",\"東京ディズニーランド\"]"
            ]
        ];

        // OpenAIにリクエストを送信
        $result = OpenAI::chat()->create([
            'model' => 'gpt-4',
            'messages' => $messages,
        ]);

        // OpenAIの返答から候補地を抽出
        $generateDestinations = response()->json([$result->choices[0]->message->content]);

        return $generateDestinations;
    }
}

