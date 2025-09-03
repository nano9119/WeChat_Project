<!DOCTYPE html>
<html lang="ar">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Weechat</title>

    <!-- CSS -->
    <link rel="stylesheet" href="{{ asset('css/chat.css') }}">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">

    <!-- Scripts -->
    <script src="https://cdn.jsdelivr.net/npm/axios/dist/axios.min.js"></script>
    <meta name="csrf-token" content="{{ csrf_token() }}">
</head>
<body class="light-mode">

<div class="chat-container">

    <!-- رأس الدردشة -->
    <header class="chat-header">
        <h1 id="chat-title">Weechat</h1>
        <button class="icon-button" id="settings-button" title="الإعدادات">
    <i class="fas fa-cog"></i>
</button>

    </header>

    <!-- الرسائل -->
    <main class="chat-main">
        <div class="message-list" id="message-list">

            {{-- رسائل نصية --}}

            {{-- الملفات --}}
           @foreach($items as $item)
    <div class="message-item {{ $item->user_id === auth()->id() ? 'my-message' : 'other-message' }}" 
         data-id="{{ $item->id }}" 
         data-type="{{ isset($item->message) ? 'message' : 'file' }}">

        <span class="message-user">{{ $item->user->name }}</span>

        @if(isset($item->message)) 
            {{-- رسالة نصية --}}
            <div class="message">{{ $item->message }}</div>

        @elseif(isset($item->file_path))
            {{-- ملف --}}
            @php
                $extension = strtolower(pathinfo($item->file_path, PATHINFO_EXTENSION));
            @endphp
            <div class="message-file">
                @if(in_array($extension, ['jpg','jpeg','png','gif','webp']))
                    <img src="{{ asset('storage/' . $item->file_path) }}" class="chat-image">
                @elseif(in_array($extension, ['mp4','webm','ogg']))
                    <video controls class="chat-video">
                        <source src="{{ asset('storage/' . $item->file_path) }}" type="video/{{ $extension }}">
                        المتصفح لا يدعم عرض الفيديو
                    </video>
                @elseif(in_array($extension, ['mp3','wav','aac','m4a','oga']))
                    <audio controls class="chat-audio">
                        <source src="{{ asset('storage/' . $item->file_path) }}" type="audio/{{ $extension }}">
                        المتصفح لا يدعم تشغيل الصوت
                    </audio>
                @else
                    <a href="{{ asset('storage/' . $item->file_path) }}" target="_blank">
                        <i class="fas fa-file"></i> {{ basename($item->file_path) }}
                    </a>
                @endif
            </div>
        @endif

        <span class="message-time">{{ $item->created_at->format('H:i') }}</span>
    </div>
    @endforeach
        </div>
        <div id="chat-end"></div>
    </main>

    <!-- إدخال الرسالة والملف -->
    <form method="POST" action="{{ route('chat.sendFile') }}" enctype="multipart/form-data" id="chat-form" class="chat-input-area">
        @csrf

        <!-- زر اختيار الملف (مشبك) -->
        <input type="file" id="media-input" name="file_path"
               accept="image/*,video/*,audio/*" style="display:none;">

        <label for="media-input" class="icon-button file-upload-button" title="إرسال ملف">
            <i class="fas fa-paperclip"></i>
        </label>

        <!-- نص الرسالة -->
        <textarea name="message" id="message-input" placeholder="اكتب رسالتك..." rows="1"></textarea>

        <!-- زر الإرسال -->
        <button type="submit" class="icon-button send-button" title="إرسال">
            <i class="fas fa-paper-plane"></i>
        </button>
    </form>
</div>

<!-- نافذة الإعدادات -->
<div class="modal-overlay" id="settings-modal-overlay">
    <div class="modal-content">
        <div class="modal-header">
            <h2>الإعدادات</h2>
            
            <button class="icon-button close-modal-button" id="close-settings-modal">
                <i class="fas fa-times"></i>
            </button>
        </div>

        <div class="modal-body">
            <div class="setting-item">
                <label for="user-name-input">اسم المستخدم:</label>
                <input type="text" id="user-name-input" value="{{ auth()->user()->name }}">
                <button class="save-name-button" id="save-name-button">حفظ</button>
            </div>

            <div class="setting-item">
                <span>الوضع الداكن:</span>
                <label class="switch">
                    <input type="checkbox" id="dark-mode-toggle">
                    <span class="slider round"></span>
                </label>
            </div>
        </div>
           <div class="setting-item">
                <a href="{{ route('exit') }}" class="logoutButton" id="Button-Exit">
                    <i class="fas fa-door-open"></i> تسجيل خروج 
                </a>
            </div>
        </div>
    </div>
</div>

<!-- قائمة خيارات الرسالة -->
<div class="message-context-menu" id="message-context-menu">
    <button id="modify-message-button"><i class="fas fa-edit"></i>✏️ تعديل</button>
    <button id="delete-message-button"><i class="fas fa-trash-alt"></i>🗑️ حذف</button>
</div>

<!-- روابط المسارات -->
<script>
    const sendMessageUrl = "{{ route('chat.sendMessage') }}";
    const sendFileUrl = "{{ route('chat.sendFile') }}";
</script>

<!-- نقطة نهاية المحادثة -->
<div id="chat-end"></div>

<!-- اختيار ملف جديد للتعديل -->
<input type="file" id="file-edit-input" style="display:none;" accept="image/*,video/*,audio/*">

<script>
document.addEventListener('DOMContentLoaded', function () {
    const form = document.getElementById('chat-form');
    const mediaInput = document.getElementById('media-input');
    const messageInput = document.getElementById('message-input');
    const contextMenu = document.getElementById('message-context-menu');
    const fileEditInput = document.getElementById('file-edit-input');
    const darkModeToggle = document.getElementById('dark-mode-toggle');
    const settingsButton = document.getElementById('settings-button');
    const closeSettingsModalButton = document.getElementById('close-settings-modal');
    const settingsModalOverlay = document.getElementById('settings-modal-overlay');
    const exitButton = document.getElementById('Button-Exit');
    const userNameInput = document.getElementById('user-name-input');
    const chatTitle = document.getElementById('chat-title');
    let currentMessage = null;

let currentMessageId = null;
let currentItemType = null;
    axios.defaults.headers.common['X-CSRF-TOKEN'] = document.querySelector('meta[name="csrf-token"]').getAttribute('content');

    function scrollToBottom() {
        const endElement = document.getElementById('chat-end');
        if (endElement) {
            endElement.scrollIntoView({ behavior: 'auto', block: 'end' });
        }
    }

    scrollToBottom();

    form.addEventListener('submit', function (e) {
        if (mediaInput.files.length > 0) {
            form.action = "{{ route('chat.sendFile') }}";
        } else if (messageInput.value.trim() !== '') {
            form.action = "{{ route('chat.sendMessage') }}";
        } else {
            e.preventDefault();
            alert('❌ الرجاء كتابة رسالة أو اختيار ملف.');
        }
        setTimeout(scrollToBottom, 100);
    });


document.getElementById('message-list').addEventListener('contextmenu', function (e) {
    const item = e.target.closest('.message-item');
    if (!item) return;
    e.preventDefault();
    currentMessage = item;

    currentMessageId = currentMessage.getAttribute('data-id');
    currentItemType = currentMessage.getAttribute('data-type');

    contextMenu.style.top = `${e.pageY}px`;
    contextMenu.style.left = `${e.pageX}px`;
    contextMenu.style.display = 'block';
});


    document.addEventListener('click', function () {
        contextMenu.style.display = 'none';
    });

   document.getElementById('modify-message-button').addEventListener('click', function () {
    if (!currentMessage) return;

    if (currentItemType === 'message') {
        const currentText = currentMessage.querySelector('.message')?.innerText || '';
        const newText = prompt('📝 تعديل الرسالة:', currentText);
        if (newText && newText !== currentText) {
            axios.put(`/chat/message/${currentMessageId}`, { message: newText })
                .then(response => {
                    if (response.data.success) {
                        currentMessage.querySelector('.message').innerText = newText;
                    }
                })
                .catch(error => {
                    alert('❌ فشل التعديل');
                    console.error(error);
                });
        }
    }

    if (currentItemType === 'file') {
        let oldPath = null;
        const img = currentMessage.querySelector('img');
        const source = currentMessage.querySelector('source');
        const link = currentMessage.querySelector('a');

        if (img) oldPath = img.src.replace('/storage/', '');
        else if (source) oldPath = source.src.replace('/storage/', '');
        else if (link) oldPath = link.href.replace('/storage/', '');
        else return alert('❌ لا يمكن تحديد الملف القديم');

        fileEditInput.click();

        fileEditInput.onchange = function () {
            const newFile = fileEditInput.files[0];
            if (!newFile) return;

            const formData = new FormData();
            formData.append('file', newFile);
            formData.append('old_file_path', oldPath);

            axios.post(`/chat/file/${currentMessageId}/update`, formData)
                .then(response => {
                    if (response.data.success) {
                        alert('✅ تم تعديل الملف');
                        location.reload();
                    } else {
                        alert('❌ فشل التعديل');
                    }
                })
                .catch(error => {
                    alert('❌ حدث خطأ أثناء التعديل');
                    console.error(error);
                });
        };
    }
});


    document.getElementById('delete-message-button').addEventListener('click', function () {
        if (!currentMessage) return;

        const itemId = currentMessage.getAttribute('data-id');
        const itemType = currentMessage.getAttribute('data-type');
        const deleteUrl = itemType === 'message' ? `/chat/message/${itemId}` : `/chat/file/${itemId}`;

        axios.delete(deleteUrl)
            .then(response => {
                if (response.data.success) {
                    currentMessage.remove();
                    setTimeout(scrollToBottom, 100);
                } else {
                    alert('❌ فشل الحذف من السيرفر');
                }
            })
            .catch(error => {
                alert('❌ حدث خطأ أثناء الحذف');
                console.error(error);
            });
    });

    settingsButton.addEventListener('click', () => {
        settingsModalOverlay.style.display = 'flex';
        setTimeout(() => settingsModalOverlay.classList.add('active'), 10);
    });

    closeSettingsModalButton.addEventListener('click', () => {
        settingsModalOverlay.classList.remove('active');
        settingsModalOverlay.addEventListener('transitionend', function handler() {
            settingsModalOverlay.style.display = 'none';
            settingsModalOverlay.removeEventListener('transitionend', handler);
        });
    });

    settingsModalOverlay.addEventListener('click', function (e) {
        if (e.target === settingsModalOverlay) {
            closeSettingsModalButton.click();
        }
    });

    if (localStorage.getItem('darkMode') === 'enabled') {
        document.body.classList.add('dark-mode');
        darkModeToggle.checked = true;
    }

    darkModeToggle.addEventListener('change', function () {
        const isDark = darkModeToggle.checked;
        document.body.classList.toggle('dark-mode', isDark);
        localStorage.setItem('darkMode', isDark ? 'enabled' : 'disabled');
    });

    const savedName = localStorage.getItem('userName');
    if (savedName) {
        userNameInput.value = savedName;
        chatTitle.textContent = "Weechat " + savedName;
    }

    exitButton.addEventListener('click', function () {
        window.location.href = '/exit';
    });
});
// document.addEventListener('click', function(e) {
//     if (!e.target.closest('#message-context-menu')) {
//         contextMenu.style.display = 'none';
//     }
// });
</script>
</body>
</html>
