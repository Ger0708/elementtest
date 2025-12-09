<!DOCTYPE html>
<html lang="zh-TW">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>元素鍊金術士：網格與輸入 DEMO</title>
    <style>
        /* --- CSS 樣式 --- */
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            margin: 0;
            padding: 20px;
            background-color: #333;
            color: #eee;
            display: flex;
            flex-direction: column;
            align-items: center;
        }

        .container {
            width: 100%;
            max-width: 600px;
            background: #444;
            padding: 20px;
            border-radius: 12px;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.5);
            margin-bottom: 20px;
        }

        /* 網格戰場容器 */
        #game-grid {
            display: grid;
            /* 網格大小 N*N = 5*5 */
            grid-template-columns: repeat(5, 1fr);
            grid-template-rows: repeat(5, 1fr);
            width: 100%;
            max-width: 400px;
            aspect-ratio: 1 / 1; /* 保持正方形 */
            border: 4px solid #5a5a5a;
            margin: 20px auto;
            background-color: #2a2a2a;
        }

        /* 網格單元格 */
        .grid-cell {
            border: 1px solid #5a5a5a;
            display: flex;
            align-items: center;
            justify-content: center;
            position: relative;
        }

        /* 元素方塊 */
        .element {
            width: 90%;
            height: 90%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 20px;
            font-weight: bold;
            border-radius: 6px;
            transition: transform 0.1s;
            box-sizing: border-box;
            user-select: none;
        }

        .fire { background-color: #ff5555; color: white; }
        .earth { background-color: #8b4513; color: white; }

        /* 選中元素樣式 */
        .selected {
            border: 4px solid #fffb00;
            box-shadow: 0 0 10px #fffb00;
        }

        /* 虛擬搖桿 (手機專用) */
        #joystick-area {
            display: none; /* 預設隱藏，JS 會根據設備顯示 */
            position: fixed;
            bottom: 20px;
            left: 20px;
            width: 120px;
            height: 120px;
            background-color: rgba(255, 255, 255, 0.1);
            border-radius: 50%;
            touch-action: none; /* 防止瀏覽器滾動 */
        }

        /* 融合按鈕 (手機專用) */
        #merge-button {
            display: none;
            position: fixed;
            bottom: 30px;
            right: 30px;
            padding: 15px 25px;
            font-size: 18px;
            background-color: #4CAF50;
            color: white;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            z-index: 100;
        }
        
        /* 設備信息 */
        #device-info {
            margin-top: 15px;
            font-size: 14px;
            color: #aaa;
        }

    </style>
</head>
<body>

    <div class="container">
        <h2>元素鍊金術士：網格合成測試</h2>
        <div id="device-info"></div>
        <p>當前選中元素位置：<span id="current-pos">無</span></p>
        
        <div id="game-grid">
            </div>

        <button onclick="placeInitialElements()">放置初始元素 (測試用)</button>
        <button onclick="doMerge()">合成 (Enter/滑桿按鈕)</button>
    </div>

    <div id="joystick-area"></div>
    <button id="merge-button" onclick="doMerge()">✨ 融合 (Merge)</button>

    <script>
        // --- JavaScript 邏輯 ---

        // 1. 遊戲核心狀態與配置
        const GRID_SIZE = 5; // N x N 網格
        let isMobile = false; // 設備偵測結果

        // 元素網格狀態：儲存每個格子裡的元素物件
        // grid[row][col] = { type: 'fire', id: 'f1', ... } 或 null
        let grid = Array.from({ length: GRID_SIZE }, () => Array(GRID_SIZE).fill(null));

        // 選中元素的位置
        let selectedElementPos = { row: -1, col: -1 }; 

        // 基礎元素數據 (僅用於 DEMO 渲染)
        const ELEMENT_DATA = {
            'fire': { name: '火', emoji: '🔥', class: 'fire', tier: 1 },
            'earth': { name: '地', emoji: '🌿', class: 'earth', tier: 1 },
            'magma': { name: '岩漿', emoji: '🌋', class: 'fire', tier: 2 },
        };
        
        // DOM 元素引用
        const gameGridEl = document.getElementById('game-grid');
        const deviceInfoEl = document.getElementById('device-info');
        const currentPosEl = document.getElementById('current-pos');
        const joystickAreaEl = document.getElementById('joystick-area');
        const mergeButtonEl = document.getElementById('merge-button');


        // 2. 設備偵測與初始化

        function detectDevice() {
            // 簡易的移動設備偵測
            isMobile = /Mobi|Android/i.test(navigator.userAgent) || (window.innerWidth <= 768 && 'ontouchstart' in window);

            if (isMobile) {
                deviceInfoEl.textContent = '偵測到：手機/平板 (使用滑桿與按鈕)';
                joystickAreaEl.style.display = 'block';
                mergeButtonEl.style.display = 'block';
                setupMobileInput();
            } else {
                deviceInfoEl.textContent = '偵測到：電腦/桌面 (使用 WASD 鍵)';
                joystickAreaEl.style.display = 'none';
                mergeButtonEl.style.display = 'none';
                setupKeyboardInput();
            }
        }
        
        // 3. 網格與渲染

        /** 創建初始的 N*N 網格 DOM */
        function createGridDOM() {
            gameGridEl.innerHTML = '';
            for (let r = 0; r < GRID_SIZE; r++) {
                for (let c = 0; c < GRID_SIZE; c++) {
                    const cell = document.createElement('div');
                    cell.className = 'grid-cell';
                    cell.dataset.row = r;
                    cell.dataset.col = c;
                    cell.onclick = () => selectElement(r, c); // 點擊選擇元素
                    gameGridEl.appendChild(cell);
                }
            }
        }

        /** 將 grid 狀態渲染到 DOM 上 */
        function renderGrid() {
            const cells = gameGridEl.querySelectorAll('.grid-cell');
            cells.forEach(cell => {
                const r = parseInt(cell.dataset.row);
                const c = parseInt(cell.dataset.col);
                
                // 清空單元格內容
                cell.innerHTML = '';
                cell.classList.remove('selected-cell');

                const element = grid[r][c];

                if (element) {
                    const data = ELEMENT_DATA[element.type];
                    const elementEl = document.createElement('div');
                    elementEl.className = `element ${data.class}`;
                    elementEl.textContent = data.emoji;
                    
                    if (r === selectedElementPos.row && c === selectedElementPos.col) {
                        elementEl.classList.add('selected');
                        currentPosEl.textContent = `(${r}, ${c}) - ${data.name}`;
                    }
                    cell.appendChild(elementEl);
                }
            });
            
            // 如果沒有選中元素，重設顯示
            if (selectedElementPos.row === -1) {
                 currentPosEl.textContent = '無';
            }
        }

        /** 測試功能：放置兩個初始元素 */
        function placeInitialElements() {
            // 清空網格
            grid = Array.from({ length: GRID_SIZE }, () => Array(GRID_SIZE).fill(null));
            selectedElementPos = { row: -1, col: -1 };
            
            // 放置元素
            grid[2][2] = { type: 'fire', id: 'f1', tier: 1 };
            grid[2][3] = { type: 'fire', id: 'f2', tier: 1 };
            grid[4][0] = { type: 'earth', id: 'e1', tier: 1 };
            
            selectElement(2, 2); // 預設選中 (2, 2)
            renderGrid();
        }

        // 4. 輸入與移動邏輯

        /** 選擇網格上的元素 */
        function selectElement(r, c) {
            if (grid[r][c]) {
                selectedElementPos = { row: r, col: c };
                renderGrid();
            } else {
                // 如果點擊空地，取消選擇
                selectedElementPos = { row: -1, col: -1 };
                renderGrid();
            }
        }

        /** 移動選中的元素 */
        function moveSelectedElement(dr, dc) {
            const { row: r, col: c } = selectedElementPos;
            if (r === -1) return;

            const newR = r + dr;
            const newC = c + dc;

            // 檢查邊界
            if (newR >= 0 && newR < GRID_SIZE && newC >= 0 && newC < GRID_SIZE) {
                // 檢查目標位置是否為空
                if (grid[newR][newC] === null) {
                    // 移動元素數據
                    grid[newR][newC] = grid[r][c];
                    grid[r][c] = null;
                    
                    // 更新選中位置
                    selectedElementPos = { row: newR, col: newC };
                    renderGrid();
                } else {
                    console.log("移動失敗：目標格子已被佔據");
                    // 這裡可以加入碰撞/戰鬥邏輯
                }
            }
        }

        /** 電腦鍵盤輸入設置 */
        function setupKeyboardInput() {
            document.addEventListener('keydown', (e) => {
                let dr = 0;
                let dc = 0;

                switch (e.key.toUpperCase()) {
                    case 'W': dr = -1; break; // 上
                    case 'S': dr = 1; break;  // 下
                    case 'A': dc = -1; break; // 左
                    case 'D': dc = 1; break;  // 右
                    case 'ENTER': 
                        e.preventDefault();
                        doMerge();
                        return; // 阻止默認換行行為
                }

                if (dr !== 0 || dc !== 0) {
                    moveSelectedElement(dr, dc);
                }
            });
        }
        
        /** 手機虛擬搖桿輸入設置 (簡化版：點擊區域移動) */
        function setupMobileInput() {
            joystickAreaEl.addEventListener('touchstart', (e) => {
                e.preventDefault(); // 阻止滾動

                const rect = joystickAreaEl.getBoundingClientRect();
                const touch = e.touches[0];
                const x = touch.clientX - rect.left - rect.width / 2;
                const y = touch.clientY - rect.top - rect.height / 2;

                let dr = 0;
                let dc = 0;

                // 根據觸控位置決定方向
                if (Math.abs(x) > Math.abs(y)) {
                    dc = x > 0 ? 1 : -1;
                } else {
                    dr = y > 0 ? 1 : -1;
                }
                
                // 立即移動
                moveSelectedElement(dr, dc);
            });
        }
        
        // 5. 核心融合邏輯 (Placeholder)

        function doMerge() {
            const { row: r, col: c } = selectedElementPos;
            if (r === -1) {
                alert("請先選擇一個元素！");
                return;
            }

            const currentElement = grid[r][c];
            if (!currentElement) return;

            // 檢查周圍 3x3 範圍內是否有相同的元素可以融合
            for (let dr = -1; dr <= 1; dr++) {
                for (let dc = -1; dc <= 1; dc++) {
                    if (dr === 0 && dc === 0) continue; // 跳過自己

                    const targetR = r + dr;
                    const targetC = c + dc;

                    // 檢查邊界和目標元素
                    if (targetR >= 0 && targetR < GRID_SIZE && targetC >= 0 && targetC < GRID_SIZE) {
                        const targetElement = grid[targetR][targetC];

                        // DEMO 邏輯：檢查是否是相同 Tier 1 元素
                        if (targetElement && 
                            targetElement.type === currentElement.type && 
                            targetElement.tier === 1) {
                            
                            // 執行融合：移除兩個 Tier 1 元素，生成一個 Tier 2 元素
                            
                            // 1. 移除目標元素
                            grid[targetR][targetC] = null;
                            
                            // 2. 將當前元素升級
                            grid[r][c] = { 
                                type: 'magma', // 假設火+火=岩漿
                                id: 'm1', 
                                tier: 2 
                            };
                            
                            // 3. 提示成功
                            alert(`✨ 成功融合! 獲得 ${ELEMENT_DATA['magma'].name}`);
                            renderGrid();
                            return; // 一旦成功融合，就退出
                        }
                    }
                }
            }
            alert("周圍沒有可融合的相同元素！");
        }


        // 6. 啟動遊戲
        
        document.addEventListener('DOMContentLoaded', () => {
            detectDevice();
            createGridDOM();
            placeInitialElements(); // 啟動時放置初始元素
        });

    </script>
</body>
</html>
