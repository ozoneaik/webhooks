<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ประเมินประสิทธิภาพการตอบแชท</title>
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Kanit:wght@300;400;500&display=swap');

        :root {
            --primary-color: #FF8C00;
            --secondary-color: #FFA500;
            --background-color: #FFF5E6;
            --text-color: #333;
        }

        body {
            font-family: 'Kanit', sans-serif;
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
            margin: 0;
            background-color: var(--background-color);
            padding: 20px;
            box-sizing: border-box;
        }
        .rating-container {
            text-align: center;
            background-color: white;
            padding: 30px;
            border-radius: 15px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
            max-width: 400px;
            width: 100%;
        }
        h2 {
            color: var(--primary-color);
            margin-bottom: 20px;
            font-weight: 500;
            font-size: 1.5rem;
            line-height: 1.3;
        }
        .stars {
            font-size: 48px;
            cursor: pointer;
            margin-bottom: 20px;
        }
        .star {
            color: #FFE4B5;
            transition: color 0.3s, transform 0.3s;
            display: inline-block;
            margin: 0 5px;
        }
        .star:hover {
            transform: scale(1.1);
        }
        .star.active {
            color: var(--primary-color);
        }
        #confirmBtn {
            display: none;
            margin-top: 20px;
            padding: 12px 25px;
            background-color: var(--primary-color);
            color: white;
            border: none;
            border-radius: 25px;
            cursor: pointer;
            font-size: 16px;
            font-weight: 500;
            transition: background-color 0.3s, transform 0.3s;
        }
        #confirmBtn:hover {
            background-color: var(--secondary-color);
            transform: translateY(-2px);
        }
        #thankYouMessage {
            display: none;
            margin-top: 20px;
            font-size: 18px;
            color: var(--primary-color);
            font-weight: 500;
            animation: fadeIn 0.5s;
        }
        @keyframes fadeIn {
            from { opacity: 0; }
            to { opacity: 1; }
        }

        @media (max-width: 480px) {
            .rating-container {
                padding: 20px;
            }
            h2 {
                font-size: 1.3rem;
            }
            .stars {
                font-size: 40px;
            }
            .star {
                margin: 0 3px;
            }
        }
    </style>
</head>
<body>
<div class="rating-container">
    <h2>เรียนคุณ {{$custName}}</h2>
    <p>กรุณาประเมินประสิทธิภาพ
        <br>
        ในการตอบแชทเรา
    </p>
    <div class="stars">
        <span class="star" data-value="1">★</span>
        <span class="star" data-value="2">★</span>
        <span class="star" data-value="3">★</span>
        <span class="star" data-value="4">★</span>
        <span class="star" data-value="5">★</span>
    </div>
    <button id="confirmBtn" style="display: none;">ยืนยัน</button>
    <div id="thankYouMessage" style="display: none;">ขอบคุณสำหรับคะแนนของคุณ!</div>
</div>


<script src="https://cdn.jsdelivr.net/npm/axios/dist/axios.min.js"></script>
<script>
    const stars = document.querySelectorAll('.star');
    const confirmBtn = document.getElementById('confirmBtn');
    const thankYouMessage = document.getElementById('thankYouMessage');
    let selectedRating = {{ $star }}; // ค่าจาก backend
    let rateId = {{ $rateId }};
    let custId = '{{ $custId }}';

    // เมื่อโหลดหน้า ถ้า selectedRating > 0 ให้แสดงข้อความขอบคุณทันที
    if (selectedRating > 0) {
        thankYouMessage.style.display = 'block';
        highlightStars(selectedRating); // แสดงดาวที่เลือกแล้ว
    }

    stars.forEach(star => {
        star.addEventListener('click', () => {
            if (selectedRating === 0) { // ให้เลือกได้เฉพาะถ้ายังไม่ได้ให้คะแนน
                selectedRating = parseInt(star.getAttribute('data-value'));
                updateStars();
                confirmBtn.style.display = 'inline-block';
                disableStars();
            }else{
                disableStars()
            }
        });

        star.addEventListener('mouseover', () => {
            const value = parseInt(star.getAttribute('data-value'));
            highlightStars(value);
        });

        star.addEventListener('mouseout', () => {
            highlightStars(selectedRating);
        });
    });

    confirmBtn.addEventListener('click', () => {

        // alert(selectedRating+rateId);
        // axios.get(`${url}/rate/${selectedRating}/${rateId}`)
        axios({
            method: 'get',
            url: `/rate/${selectedRating}/${rateId}`,
            responseType: 'stream'
        })
            .then(function (response) {
                if(response.status === 200){
                    confirmBtn.style.display = 'none';
                    thankYouMessage.style.display = 'block';
                }
            });
        // จัดการการส่งคะแนนไปที่ backend ตรงนี้
    });

    function updateStars() {
        highlightStars(selectedRating);
    }

    function highlightStars(count) {
        stars.forEach((star, index) => {
            star.classList.toggle('active', index < count);
        });
    }

    function disableStars() {
        stars.forEach(star => {
            star.style.pointerEvents = 'none'; // ปิดการคลิก
        });
    }
</script>

</body>
</html>
