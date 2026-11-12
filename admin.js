// ================= ЛОГИКА АДМИНКИ =================

// Проверяем, залогинен ли уже кто-то при загрузке страницы
window.onload = function() {
    const savedRole = sessionStorage.getItem('userRole');
    if (savedRole) {
        showDashboard(savedRole);
    }
};

function doLogin() {
    const user = document.getElementById('user').value;
    const pass = document.getElementById('pass').value;
    const role = document.getElementById('roleSelect').value;

    let isValid = false;

    // Проверка данных
    if (role === 'admin' && user === 'admin' && pass === '1234') {
        isValid = true;
    } else if (role === 'operator' && user === 'operator' && pass === '0000') {
        isValid = true;
    }

    if (isValid) {
        sessionStorage.setItem('userRole', role);
        showDashboard(role);
        document.getElementById('logErr').style.display = 'none';
    } else {
        document.getElementById('logErr').style.display = 'block';
    }
}

function showDashboard(role) {
    document.getElementById('loginBox').style.display = 'none';
    document.getElementById('dash').style.display = 'flex';
    
    // Настраиваем интерфейс под роль
    const sideTitle = document.getElementById('sideTitle');
    if (role === 'operator') {
        sideTitle.textContent = 'CDC (Оператор)';
    } else {
        sideTitle.textContent = 'CDC GALAXY (Админ)';
    }

    renderTable(role);
}

function doLogout() {
    sessionStorage.removeItem('userRole');
    document.getElementById('admin').classList.remove('on'); // Если используется как модалка
    window.location.reload(); // Перезагружаем страницу, чтобы сбросить всё
}

// Отрисовка таблицы
function renderTable(role) {
    const arr = JSON.parse(localStorage.getItem('bookings')) || [];
    const tb = document.getElementById('tbody');
    
    if (arr.length === 0) {
        tb.innerHTML = '<tr><td colspan="6" style="text-align:center;color:#888;padding:20px">Нет новых заявок</td></tr>';
        return;
    }

    tb.innerHTML = arr.map((b, i) => {
        // Если роль оператора — кнопку удаления не показываем
        let actionBtn = '';
        if (role === 'admin') {
            actionBtn = `<button class="del-btn" onclick="delBook(${i})">Удалить</button>`;
        } else {
            actionBtn = `<span style="color:#888;font-size:.8rem">Нет прав</span>`;
        }

        return `<tr>
            <td>${arr.length - i}</td>
            <td><strong>${b.name}</strong></td>
            <td>${b.phone}</td>
            <td>${b.date}</td>
            <td style="color:#888;font-size:.8rem">${b.timestamp}</td>
            <td>${actionBtn}</td>
        </tr>`;
    }).join('');
}

// Удаление заявки
function delBook(i) {
    // Проверка на всякий случай
    const role = sessionStorage.getItem('userRole');
    if (role !== 'admin') {
        alert('У вас нет прав на удаление заявок!');
        return;
    }

    if (confirm('Удалить эту заявку?')) {
        let arr = JSON.parse(localStorage.getItem('bookings')) || [];
        arr.splice(i, 1);
        localStorage.setItem('bookings', JSON.stringify(arr));
        renderTable('admin'); // Перерисовываем
    }
}