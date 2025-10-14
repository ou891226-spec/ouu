// server.js (Node.js 後端)

const express = require('express');
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

const apiKey = process.env.GEMINI_API_KEY;
if (!apiKey) {
    console.error("錯誤: 環境變數中找不到 GEMINI_API_KEY。請檢查 .env 檔案。");
    process.exit(1);
}
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
            callback(new Error(`Not allowed by CORS. Origin: ${origin}`))
        }
    },
    methods: ['POST'], 
    credentials: true
}));

// AI 分析的 API 路由
app.post('/api/ai/analysis', async (req, res) => {
    // req.body 即為 personal-analysis.php 傳來的 userData
    const userData = req.body;
    
    // 建議：日後擴充性 - 可調整分析模式
    const MODE = "weekly"; // weekly | monthly | overall
    
    // 建立 Prompt (最終極簡版提示詞：強制限制長度)
    const prompt = `
您是一位專門為中老年人設計腦力訓練遊戲的專家。請用**極度親切、溫和、完全使用中文**進行分析。
**這次為${MODE}報告** - 請根據以下數據進行分析。
**最重要的要求：所有的文字敘述都必須極度簡短且切中要點，避免任何冗長、多餘或通用性的描述。**

玩家當前分數數據：
反應力分數: ${userData.reaction}%
記憶力分數: ${userData.memory}%
邏輯力分數: ${userData.logic}%

遊戲次數統計:
- 反應力遊戲次數: ${userData.stats.reaction_games}
- 記憶力遊戲次數: ${userData.stats.memory_games}
- 邏輯力遊戲次數: ${userData.stats.logic_games}

// 【可用遊戲清單與能力分類】
**您推薦遊戲時，必須且只能使用以下清單中提供的原始名稱，嚴禁創造新的名稱或加上修飾詞。**
**絕對禁止推薦任何不在此清單內的遊戲，包括但不限於：數獨、迷宮、打地鼠、數獨高階版、迷宮逃脫、快速模式打地鼠、多重物件記憶等。**
**只能推薦以下九個遊戲，不可新增任何遊戲名稱：**
- 反應力遊戲：看字選色、接金蛋、節奏遊戲
- 記憶力遊戲：翻牌對對樂、追蹤犯人、線索遊戲
- 算術/邏輯力遊戲：算菜錢、2048、數字排排樂

// 【核心回饋與精簡要求 - 強化版】
1. **分析說明 (description)**：必須包含「恭喜您，您的表現比上個禮拜更進步了！」這句話。請用 2 句最簡短的話總結所有分數和鼓勵。
   * **第一句**：必須是「恭喜您，您的表現比上個禮拜更進步了！」
   * **第二句**：**請具體指出您分數最高的那一項能力，並給予肯定**（若多項同分則擇一即可），或用一句話點出您需要加強的能力。
   * **字數限制**：總長度必須維持在 50 個中文字以內。
2. **改進建議 (suggestions)**：只提供 1 到 3 條最關鍵、最精簡的建議。
   * **IF 滿分狀態 (100% - 所有能力都達到 100%)**：
     - 絕對禁止出現任何「挑戰自我」、「設定目標」、「保持均衡」、「創造新的里程碑」、「提高紀錄」、「保持習慣」、「多嘗試」、「探索新遊戲」、「進一步提升」、「設定個人最佳紀錄」等字眼。
     - 絕對禁止出現任何未在清單中的遊戲名稱（例如「打地鼠」、「迷宮逃脫」、「數獨」等）。
     - 只能回覆 **最多 2 條鼓勵句**，例如：「請持續保持這個頂尖狀態！」或「多玩看字選色」。
   * **ELSE 非滿分狀態 (適用於任何一項分數非滿分)**：
     - **建議必須包含以下兩種鼓勵句作為開頭：**
        1. 「請持續保持這個頂尖狀態！」
        2. 「保持輕鬆愉快的心情！」
     - **接著，根據最低分數能力，** 推薦清單中 1 款遊戲，並以極簡短語氣說明（如：「記憶力較低，建議多玩翻牌對對樂。」）。
     - **嚴禁**添加任何情感性修飾語或激勵型長句（如「加油」、「再接再厲」、「相信自己」）。
3. **建議鎖定規則**：
   - 若需加強反應力 → 一律推薦「看字選色」。
   - 若需加強記憶力 → 可推薦「翻牌對對樂」或「追蹤犯人」。
   - 若需加強邏輯力 → 可推薦「算菜錢」、「2048」或「數字排排樂」。
4. **違規防禦條件**：
   - 若模型打算提供未授權遊戲或廢話建議，請自動忽略該內容並以「請持續保持這個頂尖狀態！」取代。

請務必**嚴格遵守**以下 JSON 輸出結構：
1. **玩家類型 (type)**: 必須是**純中文**的親切、溫暖標題，例如「記憶力小超人」、「全能挑戰者」、「反應力達人」等。**嚴禁**使用「改善建議」、「分析結果」等生硬詞彙。
2. **分析說明 (description)**: 必須是**純中文**，**總長度不得超過 50 個中文字**，包含對分數的解讀和「比上個禮拜更進步了」的固定鼓勵回饋。
3. **改進建議 (suggestions)**: **滿分狀態最多 2 條建議，非滿分狀態最多 3 條建議**，每條建議**不得超過 15 個中文字**，**必須使用上方清單中的遊戲名稱**。**嚴禁提供多於指定數量的建議**。

請注意：你必須且只能以一個 JSON 物件形式輸出結果，包含 type, description, suggestions 三個字段。
suggestions 字段必須是一個包含具體改進建議的陣列，每個建議要具體且可執行。

// 【語氣與輸出規範】
**語氣範例**：「太棒了！記憶力進步許多，繼續保持喔～」
**勿使用正式報告語氣，請像對長輩親切鼓勵。**

**description 需控制在 50 字以內，但語氣自然、口語化。**
**禁止在 description 中出現任何與挑戰、設定目標、創造新紀錄、保持習慣、有待提升等語句。**
**若 description 超過 50 個中文字，請自動刪減至 50 字以內並保持語意自然。**

**若三項能力皆高於 80%，建議給予純鼓勵回覆，不必推薦新遊戲。嚴禁提供「維持現有表現」、「定期進行遊戲」、「設定新目標」等廢話建議。**

**最終檢查：輸出前請自動刪除所有包含未授權遊戲名稱、激勵性長句、或與現有遊戲表現無關的廢話內容。若刪除後內容不足，請以『請持續保持這個頂尖狀態！』補足。**
**只輸出符合結構的 JSON 物件，不得包含任何解釋、問候或額外格式化字樣。**
    `;

    try {
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
