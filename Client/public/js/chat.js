document.addEventListener('DOMContentLoaded', () => {
    // إعداد CSRF و Authorization
    axios.defaults.headers.common['X-CSRF-TOKEN'] =
        document.querySelector('meta[name="csrf-token"]').getAttribute('content');
    const token = localStorage.getItem('token');
    if (token) axios.defaults.headers.common['Authorization'] = `Bearer ${token}`;

    // عناصر DOM
    const messageInput = document.getElementById('message-input');
    const sendButton = document.getElementById('send-button');
    const messageList = document.getElementById('message-list');
    const settingsButton = document.getElementById('settings-button');
    const settingsModalOverlay = document.getElementById('settings-modal-overlay');
    const closeSettingsModalButton = document.getElementById('close-settings-modal');
    const userNameInput = document.getElementById('user-name-input');
    const saveNameButton = document.getElementById('save-name-button');
    const chatTitle = document.getElementById('chat-title');
    const darkModeToggle = document.getElementById('dark-mode-toggle');
    const fileInput = document.getElementById('file-input');
    const messageContextMenu = document.getElementById('message-context-menu');
    const modifyMessageButton = document.getElementById('modify-message-button');
    const deleteMessageButton = document.getElementById('delete-message-button');
    const ExitButton = document.getElementById('Button-Exit');
    const form = document.getElementById('chat-form');
    const mediaInput = document.getElementById('media-input');

    // متغيرات عامة
    let currentUserName = localStorage.getItem('userName') || 'أنت';
    let selectedMessage = null;
    let editingMessageElement = null;

    // --- الإعدادات ---
    const loadPreferences = () => {
        userNameInput.value = currentUserName;
        chatTitle.textContent = "Weechat " + currentUserName;
        const isDarkMode = localStorage.getItem('darkMode') === 'enabled';
        document.body.classList.toggle('dark-mode', isDarkMode);
        darkModeToggle.checked = isDarkMode;
    };

    // --- رسائل ---
    const adjustTextareaHeight = () => {
        messageInput.style.height = 'auto';
        messageInput.style.height = messageInput.scrollHeight + 'px';
    };

    const scrollToBottom = () => {
        messageList.scrollTop = messageList.scrollHeight;
    };

    const createMessageElement = (text, sender, isFile = false, fileName = null, messageId = null) => {
        const messageBubble = document.createElement('div');
        messageBubble.classList.add('message-bubble', sender === currentUserName ? 'self' : 'other');
        if (messageId) messageBubble.dataset.id = messageId;

        if (isFile && fileName) {
            const filePreview = document.createElement('div');
            filePreview.classList.add('file-preview');
            const fileIcon = document.createElement('i');
            fileIcon.classList.add('fas', 'fa-file');
            filePreview.appendChild(fileIcon);

            const fileNameSpan = document.createElement('span');
            fileNameSpan.textContent = fileName;
            filePreview.appendChild(fileNameSpan);

            messageBubble.appendChild(filePreview);

            const fileMessageText = document.createElement('p');
            fileMessageText.textContent = text || 'تم إرسال ملف.';
            messageBubble.appendChild(fileMessageText);
        } else {
            const messageText = document.createElement('p');
            messageText.textContent = text;
            messageBubble.appendChild(messageText);
        }

        const timestamp = document.createElement('span');
        timestamp.classList.add('timestamp');
        const now = new Date();
        timestamp.textContent = `${now.getHours().toString().padStart(2, '0')}:${now.getMinutes().toString().padStart(2, '0')}`;
        messageBubble.appendChild(timestamp);

        messageList.appendChild(messageBubble);
        scrollToBottom();
        return messageBubble;
    };

    // --- API ---
    const fetchMessages = async () => {
        try {
            const res = await axios.get('/api/messages');
            messageList.innerHTML = '';
            res.data.forEach(msg => {
                createMessageElement(msg.message, msg.user_name, false, null, msg.id);
            });
        } catch (err) {
            console.error('فشل جلب الرسائل', err);
        }
    };

    const sendMessageToAPI = async (text) => {
        if (!text.trim()) return;
        try {
            const res = await axios.post('/api/messages', {
                user_name: currentUserName,
                message: text
            });
            const msg = res.data.message;
            createMessageElement(msg.message, msg.user_name, false, null, msg.id);
            messageInput.value = '';
        } catch (err) {
            console.error('فشل إرسال الرسالة', err);
        }
    };

    const deleteMessageFromAPI = async (id) => {
        try {
            await axios.delete(`/api/messages/${id}`);
            fetchMessages();
        } catch (err) {
            console.error('فشل حذف الرسالة', err);
        }
    };

    const updateMessageInAPI = async (id, newText) => {
        try {
            await axios.put(`/api/messages/${id}`, { text: newText });
            fetchMessages();
        } catch (err) {
            console.error('فشل تعديل الرسالة', err);
        }
    };

    // --- إرسال الرسائل ---
    const sendMessage = (text, isFile = false, fileName = null, fileData = null) => {
        if (text.trim() === '' && !isFile) return;
        sendMessageToAPI(text);
        messageInput.value = '';
        adjustTextareaHeight();
    };

    sendButton.addEventListener('click', () => sendMessage(messageInput.value));
    messageInput.addEventListener('keypress', e => {
        if (e.key === 'Enter' && !e.shiftKey) {
            e.preventDefault();
            sendMessage(messageInput.value);
        }
    });
    messageInput.addEventListener('input', adjustTextareaHeight);

    // --- رفع الملفات ---
    fileInput.addEventListener('change', e => {
        const file = e.target.files[0];
        if (file) {
            sendMessage('تم إرسال ملف:', true, file.name, file);
            fileInput.value = '';
        }
    });

    // --- تعديل وحذف الرسائل ---
    const hideContextMenu = () => {
        messageContextMenu.style.display = 'none';
        selectedMessage = null;
    };

    messageList.addEventListener('contextmenu', e => {
        const messageBubble = e.target.closest('.message-bubble');
        if (messageBubble && messageBubble.classList.contains('self')) {
            e.preventDefault();
            if (selectedMessage) selectedMessage.classList.remove('selected');
            selectedMessage = messageBubble;
            selectedMessage.classList.add('selected');
            messageContextMenu.style.top = `${e.clientY}px`;
            messageContextMenu.style.left = `${e.clientX}px`;
            messageContextMenu.style.display = 'block';
        } else hideContextMenu();
    });

    document.addEventListener('click', e => {
        if (!messageContextMenu.contains(e.target) && e.target.closest('.message-bubble') !== selectedMessage) {
            hideContextMenu();
            if (selectedMessage) selectedMessage.classList.remove('selected');
        }
    });

    deleteMessageButton.addEventListener('click', () => {
        if (selectedMessage && confirm('هل أنت متأكد أنك تريد حذف هذه الرسالة؟')) {
            deleteMessageFromAPI(selectedMessage.dataset.id);
            hideContextMenu();
        }
    });

    modifyMessageButton.addEventListener('click', () => {
        if (!selectedMessage || editingMessageElement) return;

        const messageTextElement = selectedMessage.querySelector('p');
        const originalText = messageTextElement.textContent;

        const editArea = document.createElement('textarea');
        editArea.value = originalText;
        editArea.classList.add('edit-message-input');
        editArea.style.width = '100%';
        editArea.style.minHeight = '40px';

        const actionsDiv = document.createElement('div');
        actionsDiv.style.display = 'flex';
        actionsDiv.style.justifyContent = 'flex-end';
        actionsDiv.style.gap = '10px';
        const saveButton = document.createElement('button');
        saveButton.textContent = 'حفظ';
        const cancelButton = document.createElement('button');
        cancelButton.textContent = 'إلغاء';
        actionsDiv.appendChild(saveButton);
        actionsDiv.appendChild(cancelButton);

        messageTextElement.style.display = 'none';
        selectedMessage.insertBefore(editArea, messageTextElement);
        selectedMessage.appendChild(actionsDiv);
        editArea.focus();
        editingMessageElement = selectedMessage;

        saveButton.addEventListener('click', () => {
            const newText = editArea.value.trim();
            if (newText) updateMessageInAPI(selectedMessage.dataset.id, newText);
            editArea.remove(); actionsDiv.remove(); messageTextElement.style.display = 'block';
            editingMessageElement = null; hideContextMenu();
        });

        cancelButton.addEventListener('click', () => {
            editArea.remove(); actionsDiv.remove(); messageTextElement.style.display = 'block';
            editingMessageElement = null; hideContextMenu();
        });

        hideContextMenu();
    });

    // --- إعدادات واجهة ---
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

    settingsModalOverlay.addEventListener('click', e => {
        if (e.target === settingsModalOverlay) closeSettingsModalButton.click();
    });

    saveNameButton.addEventListener('click', () => {
        const newName = userNameInput.value.trim();
        if (newName) {
            currentUserName = newName;
            localStorage.setItem('userName', newName);
            chatTitle.textContent = "Weechat " + currentUserName;
            alert('تم حفظ الاسم بنجاح!');
        } else alert('الاسم لا يمكن أن يكون فارغاً.');
    });

    darkModeToggle.addEventListener('change', () => {
        document.body.classList.toggle('dark-mode', darkModeToggle.checked);
        localStorage.setItem('darkMode', darkModeToggle.checked ? 'enabled' : 'disabled');
    });

    ExitButton.addEventListener('click', e => {
        e.preventDefault();
        localStorage.removeItem('token'); // حذف التوكن عند الخروج
        window.location.href = '/login';
    });

    // --- رفع ملفات من الفورم ---
    mediaInput.addEventListener('change', function () {
        if (mediaInput.files.length > 0) {
            form.action = sendFileUrl;
            form.submit();
        }
    });

    form.addEventListener('submit', function () {
        form.action = sendMessageUrl;
    });

    // تحميل الإعدادات والرسائل
    loadPreferences();
    fetchMessages();
});
