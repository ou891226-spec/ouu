// AI分析微服務 - 使用Google Gemini API
import { GoogleGenerativeAI } from "@google/generative-ai";

// 安全地從環境變量讀取API金鑰（按照你的指南）
const ai = new GoogleGenerativeAI(process.env.GEMINI_API_KEY);

// 檢查API金鑰是否設置
if (!process.env.GEMINI_API_KEY) {
    console.error('請設置 GEMINI_API_KEY 環境變量');
    console.log('在 PowerShell 中執行：$env:GEMINI_API_KEY="您的金鑰"');
    process.exit(1);
}

// 啟動簡單的HTTP服務器
import express from 'express';
const app = express();

// 安全配置
app.use(express.json({ limit: '10mb' })); // 限制請求大小

// 來源驗證（允許本地和Azure環境）
app.use((req, res, next) => {
    const origin = req.get('origin') || req.get('referer') || '';
    const host = req.get('host') || '';
    
    // 允許本地環境和Azure環境
    const isAllowed = origin.includes('localhost') || 
                     origin.includes('127.0.0.1') || 
                     host.includes('azurewebsites.net') ||
                     origin.includes('azurewebsites.net');
    
    if (origin && !isAllowed) {
        return res.status(403).json({ error: '禁止外部訪問' });
    }
    next();
});

// AI分析端點
app.post('/analyze', async (req, res) => {
    try {
        const { userData } = req.body;
        
        // 構建分析提示詞
        const prompt = `你是一位專門分析認知表現的【樂齡智趣網資深認知教練】。
你的核心任務是根據提供的用戶數據，生成一份專業且具備高度行動性的分析報告，必須嚴格以 JSON 格式輸出。

---
### 任務要求
1. **角色設定：** 分析師必須以一位【資深教練】的視角，對玩家進行專業且鼓勵性的指導。
2. **玩家類型命名：** 根據玩家三項分數的優勢與劣勢組合，命名一個**具體、吸引人且能概括核心特點**的玩家類型名稱。
3. **分析描述：** 必須明確指出玩家的**優勢組合**（例如，反應力是邏輯力的 X 倍）和**最大潛力領域**（分數最低的項目），並解釋分數與訓練行為之間的關係。
4. **改進建議結構：** 建議部分必須包含以下三個具體類別的內容：
   * **【日常生活應用】**：如何將認知優勢應用到日常決策中。
   * **【綜合健康建議】**：結合輕微體能活動的認知訓練方法。
   * **【本月挑戰目標】**：提供一個具體、可量化（例如：記住 X 個單字）且與玩家弱項相關的目標。
5. **輸出格式：** 最終輸出必須為單一、乾淨的 JSON 格式。

---
### 數據輸入
【基礎能力數據】
- 反應力等級：${userData.reaction_level}/10 (分數：${userData.reaction_score})
- 記憶力等級：${userData.memory_level}/10 (分數：${userData.memory_score})
- 邏輯力等級：${userData.logic_level}/10 (分數：${userData.logic_score})
- 總分：${userData.total_score}

【社群比較數據】
- 反應力：${userData.ability_ratios.reaction_to_avg}倍平均值 (平均值：${userData.avg_reaction})
- 記憶力：${userData.ability_ratios.memory_to_avg}倍平均值 (平均值：${userData.avg_memory})
- 邏輯力：${userData.ability_ratios.logic_to_avg}倍平均值 (平均值：${userData.avg_logic})

【頂尖比較數據】
- 反應力與最高分比較：${userData.reaction_score}/${userData.max_reaction} (${((userData.reaction_score/userData.max_reaction)*100).toFixed(1)}%)
- 記憶力與最高分比較：${userData.memory_score}/${userData.max_memory} (${((userData.memory_score/userData.max_memory)*100).toFixed(1)}%)
- 邏輯力與最高分比較：${userData.logic_score}/${userData.max_logic} (${((userData.logic_score/userData.max_logic)*100).toFixed(1)}%)

【活躍度與成長趨勢】
- 總遊戲次數：${userData.total_games}
- 最近30天遊戲次數：${userData.recent_games}
- 最近平均分數：${userData.recent_avg_score}

【詳細遊戲表現】
${userData.game_stats.map(game => `- ${game.game_type}：${game.total_games}次 (平均：${game.avg_score}, 最高：${game.max_score})`).join('\n')}

請根據以上數據進行專業分析，並以以下 JSON 格式輸出：

{
  "type": "具體且吸引人的玩家類型名稱",
  "description": "專業分析描述，必須包含：1) 優勢組合分析（具體數值比較，重要數字請用<strong>標籤加粗）2) 最大潛力領域識別 3) 分數與訓練行為關係解釋",
  "suggestions": [
    "【日常生活應用】：具體的日常認知訓練建議（重要數字用<strong>標籤加粗）",
    "【綜合健康建議】：結合體能活動的認知訓練方法（重要數字用<strong>標籤加粗）",
    "【本月挑戰目標】：具體可量化的挑戰目標（重要數字用<strong>標籤加粗）"
  ]
}

**重要格式要求：**
- 所有重要數字（分數、百分比、倍數、目標數量等）都必須用 <strong>數字</strong> 格式加粗
- 例如：您的反應力是 <strong>750</strong> 分，是平均值的 <strong>1.5</strong> 倍
- 例如：建議本月挑戰記住 <strong>15</strong> 個新單字

請確保你的回應是純 JSON 格式，不要包含任何額外的文字或解釋。`;

        // 使用Google Gemini API
        const model = ai.getGenerativeModel({ model: "gemini-2.0-flash-exp" });
        const result = await model.generateContent(prompt);
        const response = await result.response;
        
        // 嘗試解析AI回應為JSON
        let aiAnalysis;
        try {
            // 清理回應文本，移除可能的markdown格式
            let cleanResponse = response.text().trim();
            if (cleanResponse.startsWith('```json')) {
                cleanResponse = cleanResponse.replace(/```json\n?/, '').replace(/\n?```$/, '');
            }
            
            aiAnalysis = JSON.parse(cleanResponse);
        } catch (parseError) {
            console.error('AI回應JSON解析失敗:', parseError);
            console.log('原始AI回應:', response.text());
            
            // 如果解析失敗，提供備用格式
            aiAnalysis = {
                type: "智能分析玩家",
                description: response.text() || "AI分析完成，您的表現很棒！",
                suggestions: ["繼續保持遊戲習慣", "嘗試不同類型的遊戲來提升各項能力"]
            };
        }
        
        res.json({
            success: true,
            analysis: aiAnalysis
        });
    } catch (error) {
        console.error('AI分析錯誤:', error);
        res.status(500).json({
            success: false,
            message: 'AI分析失敗'
        });
    }
});

const PORT = process.env.PORT || 3001;
app.listen(PORT, () => {
    console.log(`Node.js AI Service is running on port ${PORT}`);
    console.log(`AI分析服務運行在 http://localhost:${PORT}`);
    console.log(`PHP後端可以通過 http://localhost:${PORT}/analyze 調用AI分析`);
    console.log('按照你的指南，服務已成功啟動');
});
