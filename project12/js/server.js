// server.js (Node.js 後端)

const express = require('express');
// 由於 Gemini API 已經更新為 @google/genai，我們應該使用最新的 SDK
const { GoogleGenAI } = require('@google/genai'); 
const cors = require('cors');
require('dotenv').config(); 

const app = express();
// 讀取 .env 檔案中的 PORT，若沒有則使用 3000 作為後備
const port = process.env.PORT || 3000; 

// 替換成Azure 上的 PHP 前端服務網域
const AZURE_FRONTEND_URL = 'https://smartfun-seniors-dhhugsf2d4e7dqay.eastasia-01.azurewebsites.net'; 
// 在本地測試時
const allowedOrigins = ['http://localhost', 'http://localhost:80', 'http://localhost:3000', AZURE_FRONTEND_URL]; 

// 從環境變數中獲取 API Key
const apiKey = process.env.GEMINI_API_KEY;
if (!apiKey) {
    console.error("錯誤: 環境變數中找不到 GEMINI_API_KEY。請檢查 .env 檔案。");
    process.exit(1);
}
// 初始化 GoogleGenAI 客戶端
const ai = new GoogleGenAI({ apiKey });

// 中間件設定
app.use(express.json()); 

////////////// 解決跨域問題
app.use(cors({
    origin: function (origin, callback) {
        // 允許列表中的網域，或允許非瀏覽器請求 (如 Postman 或本地測試)
        if (!origin || allowedOrigins.includes(origin)) {
            callback(null, true)
        } else {
            // 輸出被拒絕的網域，方便除錯
            console.warn(`CORS 拒絕: Origin ${origin}`);
            callback(new Error(`Not allowed by CORS. Origin: ${origin}`))
        }
    },
    methods: ['POST'], 
    credentials: true
}));

// AI 分析的 API 路由
app.post('/api/ai/analysis', async (req, res) => {
    // req.body 即為 personal-analysis.php 傳來的 userData (包含 reaction, memory, logic, stats)
    const userData = req.body;

    // 檢查必要數據是否存在
    if (!userData || typeof userData.reaction !== 'number' || typeof userData.memory !== 'number' || typeof userData.logic !== 'number' || !userData.stats) {
        return res.status(400).json({ success: false, message: "請求數據格式不正確，缺少分數或 stats。" });
    }
    
    // 檢測用戶是否為新用戶（總遊戲次數少於5次）
    const totalGames = userData.stats.reaction_games + userData.stats.memory_games + userData.stats.logic_games;
    
    // 檢測用戶是否剛開始（總遊戲次數少於5次 或 註冊時間少於24小時）
    const isNewUser = totalGames < 5;
    
    // 檢測是否所有分數都接近0（可能是剛註冊或沒有有效遊戲記錄）
    const hasNoValidData = userData.reaction === 0 && userData.memory === 0 && userData.logic === 0;
    
    // 檢測是否為一天內的新用戶（避免顯示"比上週進步"等比較性話語）
    const isOneDayUser = totalGames < 10; // 假設一天內遊戲次數不會超過10次
    
    // 建議：日後擴充性 - 可調整分析模式
    const MODE = "weekly"; // weekly | monthly | overall
    
    // 建立 Prompt (溫暖親切的數位小幫手版本)
    const prompt = `
您是一位專門為中老年人設計腦力訓練遊戲的親切、貼心、像家人般的數位小幫手 (Digital Health Aide)。您以極度溫暖、鼓勵、口語化且完全使用中文進行分析回饋。

【情境與數據重點】

角色目標：您的核心目標是讓長輩感到被關心與肯定，並以「玩遊戲」的心態輕鬆接受建議，絕對避免使用任何學術性、生硬的、或有壓力的詞彙。

${isNewUser || hasNoValidData || isOneDayUser ? 
`【新用戶歡迎模式】
這是一位剛開始使用系統的新用戶，或是還沒有足夠遊戲記錄的用戶。請提供溫暖的歡迎訊息和鼓勵，絕對禁止使用任何比較性話語如「比上週進步」、「比之前更好」等。` : 
`【進步分析模式】
請根據玩家的表現，提供一份充滿正能量的分析報告。可以使用進步比較的話語。`}

玩家當前狀態：
- 總遊戲次數: ${totalGames} 次
- 是否為新用戶: ${isNewUser ? '是' : '否'}
- 是否為一天內用戶: ${isOneDayUser ? '是' : '否'}
- 有效數據: ${hasNoValidData ? '無' : '有'}

玩家當前分數數據：
反應力分數: ${userData.reaction}%
記憶力分數: ${userData.memory}%
邏輯力分數: ${userData.logic}%

遊戲次數統計:
- 反應力遊戲次數: ${userData.stats.reaction_games}
- 記憶力遊戲次數: ${userData.stats.memory_games}
- 邏輯力遊戲次數: ${userData.stats.logic_games}

// 【可用遊戲清單與能力分類 - 嚴格遵守】
推薦遊戲時，必須且只能使用以下清單中提供的原始名稱，嚴禁創造新的名稱或加上修飾詞。
絕對禁止推薦任何不在此清單內的遊戲。

反應力遊戲：看字選色、接金蛋、節奏遊戲
記憶力遊戲：翻牌對對樂、追蹤犯人、線索遊戲
算術/邏輯力遊戲：算菜錢、2048、數字排排樂

// 【核心回饋與精簡要求】

玩家類型 (type)：必須是純中文的親切、溫暖、高度肯定的標題。
${isNewUser || hasNoValidData || isOneDayUser ? 
`- 新用戶範例：「腦力訓練新夥伴」、「歡迎加入我們」、「準備開始的冒險者」` : 
`- 一般用戶範例：「記憶力小超人」、「全能挑戰者」、「反應力達人」`}

分析說明 (description)：
${isNewUser || hasNoValidData || isOneDayUser ? 
`- 新用戶第一句：使用歡迎語，例如「歡迎來到腦力訓練的世界！」或「很高興您開始了這段旅程！」
- 第二句：簡單說明系統會記錄遊戲表現，鼓勵多嘗試不同遊戲。
- 字數限制：總長度必須維持在 50 個中文字以內。
- 極度重要禁止詞彙：絕對禁止提到「進步」、「比上週」、「比之前更好」、「有待提升」等任何帶有比較或壓力的詞語。` :
`- 一般用戶第一句：請務必在第一句使用正能量的句子，例如「恭喜您，您的表現持續在進步中！」或「您的狀態穩定，維持得非常好！」建議優先使用包含「進步」的表達方式來給予積極肯定。
- 第二句 (肯定)：請像朋友一樣，根據實際分數數據，具體指出您分數最高的那一項能力，並給予熱情的肯定。分數數據：反應力${userData.reaction}%，記憶力${userData.memory}%，邏輯力${userData.logic}%（若多項同分則擇一即可）。
- 努力肯定：若任一遊戲次數超過 10 次，請在說明中口語化地稱讚玩家很認真（例如：「您玩了這麼多次，真厲害！」）。請將這句話融入原有的 50 字限制內。
- 字數限制：總長度必須維持在 50 個中文字以內。
- 禁止：不得出現任何與挑戰、設定目標、創造新紀錄、保持習慣、有待提升等有壓力或勸戒性質的詞語。`}

改進建議 (suggestions)：只提供 1 到 3 條最關鍵、最精簡的建議。

${isNewUser || hasNoValidData || isOneDayUser ?
`新用戶建議模式：
- 推薦嘗試 3 個不同類型的遊戲（各選一個），使用「試試看字選色(反應力)」、「試試翻牌對對樂(記憶力)」、「試試算菜錢(邏輯力)」的句型。
- 每條建議不得超過 15 個中文字。` :
`一般用戶建議模式：
IF 滿分狀態 (100% - 所有能力都達到 100%)：
- 只能回覆 最多 2 條鼓勵句。例如：「請持續保持這個頂尖狀態！」或「多玩看字選色(反應力)」。

ELSE 非滿分狀態：
- 建議必須包含鼓勵句作為開頭：「請持續保持這個頂尖狀態！」
- 接著，**根據玩家最低分數的能力**，**嚴格**執行以下隨機推薦規則，並推薦 1 款遊戲。

- **建議隨機推薦指令（請務必在遊戲名稱後加上能力分類）：**
  - IF 最低分是**反應力** → 從 [看字選色, 接金蛋, 節奏遊戲] 中**隨機挑選** 1 款，並回覆「多玩 [遊戲名稱](反應力)」。
  - IF 最低分是**記憶力** → 從 [翻牌對對樂, 追蹤犯人, 線索遊戲] 中**隨機挑選** 1 款，並回覆「多玩 [遊戲名稱](記憶力)」。
  - IF 最低分是**邏輯力** → 從 [算菜錢, 2048, 數字排排樂] 中**隨機挑選** 1 款，並回覆「多玩 [遊戲名稱](邏輯力)」。

字數限制：每條建議（含標點符號）不得超過 15 個中文字。
嚴禁添加任何情感性修飾語或激勵型長句（如「加油」、「再接再厲」）。`}

最終產出規範：
請務必嚴格遵守 JSON 輸出結構，只輸出符合結構的 JSON 物件，不得包含任何解釋、問候或額外格式化字樣。

{
"type": "...",
"description": "...",
"suggestions": [
"...",
"..."
]
}
    `;

    try {
        // 嘗試呼叫 Gemini API
        const result = await ai.models.generateContent({
            model: "gemini-2.5-flash",
            contents: [{ role: "user", parts: [{ text: prompt }] }],
            config: {
                responseMimeType: "application/json", 
                // 定義輸出結構，確保前端能正確解析 JSON
                responseSchema: {
                    type: "object",
                    properties: {
                        type: { type: "string" },
                        description: { type: "string" },
                        suggestions: { type: "array", items: { type: "string" } }
                    },
                    required: ["type", "description", "suggestions"]
                }
            }
        });

        // 補一層安全 Try-Catch 處理 AI 回傳空值情況
        if (!result.text || !result.text.trim()) {
            throw new Error("AI 未回傳有效內容");
        }
        
        const aiText = result.text.trim();
        // 解析 AI 回傳的 JSON 字符串
        const analysisData = JSON.parse(aiText);
        
        // 建議：為分析結果加入時間戳記
        analysisData.report_time = new Date().toISOString();
        
        // 傳回 JSON 數據
        res.json({
            success: true,
            report: {
                ...analysisData,
                ai_enhanced: true
            }
        });

    } catch (error) {
        console.error("GenAI API 呼叫失敗:", error);
        // 返回錯誤訊息給前端
        res.status(500).json({
            success: false,
            message: "Node.js 後端 GenAI 呼叫失敗，請檢查 API Key 或 Prompt。",
            error: error.message
        });
    }
});

app.listen(port, '0.0.0.0', () => {
    // 輸出您正在使用的 port 號
    console.log(`Node.js AI 分析伺服器已啟動，正在監聽 http://0.0.0.0:${port}`);
});
