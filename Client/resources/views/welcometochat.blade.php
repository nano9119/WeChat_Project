<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Welcome to Chat</title>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  <style>
    * {
      box-sizing: border-box;
      margin: 0;
      padding: 0;
      font-family: 'Segoe UI', sans-serif;
    }

    body {
      height: 100vh;
      display: flex;
      background: linear-gradient(to right, #0f2027, #203a43, #2c5364);
    }

    .container {
      display: flex;
      width: 100%;
      height: 100%;
    }

    .left {
      flex: 1;
      background-color: #111;
      color: white;
      display: flex;
      flex-direction: column;
      justify-content: center;
      align-items: center;
      padding: 40px;
      clip-path: polygon(0 0, 100% 0, 80% 100%, 0% 100%);
    }

    .left h1 {
      font-size: 36px;
      margin-bottom: 20px;
    }

    .left p {
      font-size: 18px;
      margin-bottom: 40px;
      color: #ccc;
      text-align: center;
    }

    .start-btn {
      background-color: #00ffff;
      color: #111;
      border: none;
      padding: 12px 30px;
      font-size: 16px;
      border-radius: 25px;
      cursor: pointer;
      transition: 0.3s ease;
    }

    .start-btn:hover {
      background-color: #00cccc;
    }

    .right {
      flex: 1;
      background: linear-gradient(135deg, #00ffff, #00cccc);
      display: flex;
      justify-content: center;
      align-items: center;
      color: white;
      font-size: 40px;
      font-weight: bold;
      clip-path: polygon(20% 0, 100% 0, 100% 100%, 0% 100%);
    }
  </style>
</head>
<body>
  <div class="container">
    <div class="left">
      <h1>Welcome to Chat</h1>
      <p>Connect instantly and start chatting with your friends.</p>
        <a href="{{ route('interfacelogin') }}" class="start-btn">Start Chat</a>
    </div>
    <div class="right">
      Welcome Back!
    </div>
  </div>
</body>
</html>
