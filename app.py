import base64
import io
import os
import sqlite3
import uuid
from datetime import datetime
from functools import wraps

import qrcode
from flask import (
    Flask,
    abort,
    flash,
    g,
    redirect,
    render_template,
    request,
    session,
    url_for,
)
from werkzeug.middleware.proxy_fix import ProxyFix
from werkzeug.security import check_password_hash, generate_password_hash

APP_DIR = os.path.dirname(os.path.abspath(__file__))
DB_PATH = os.path.join(APP_DIR, "barkod.db")
BASE_PATH = os.getenv("BASE_PATH", "/").strip() or "/"
if not BASE_PATH.startswith("/"):
    BASE_PATH = "/" + BASE_PATH
if BASE_PATH != "/" and BASE_PATH.endswith("/"):
    BASE_PATH = BASE_PATH[:-1]


class PrefixMiddleware:
    """Serve the app from a URL prefix like /barkod while keeping local / support."""

    def __init__(self, app, prefix):
        self.app = app
        self.prefix = prefix

    def __call__(self, environ, start_response):
        path = environ.get("PATH_INFO", "") or "/"
        if self.prefix != "/" and path.startswith(self.prefix):
            environ["SCRIPT_NAME"] = self.prefix
            trimmed = path[len(self.prefix) :]
            environ["PATH_INFO"] = trimmed if trimmed else "/"
        return self.app(environ, start_response)


app = Flask(__name__)
app.secret_key = os.getenv("SECRET_KEY", "dev-secret-key")
app.wsgi_app = ProxyFix(app.wsgi_app, x_proto=1, x_host=1, x_prefix=1)
app.wsgi_app = PrefixMiddleware(app.wsgi_app, BASE_PATH)


def get_db():
    if "db" not in g:
        g.db = sqlite3.connect(DB_PATH)
        g.db.row_factory = sqlite3.Row
    return g.db


@app.teardown_appcontext
def close_db(_):
    db = g.pop("db", None)
    if db is not None:
        db.close()


def init_db():
    db = sqlite3.connect(DB_PATH)
    db.executescript(
        """
        PRAGMA foreign_keys = ON;

        CREATE TABLE IF NOT EXISTS users (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            username TEXT UNIQUE NOT NULL,
            password_hash TEXT NOT NULL,
            role TEXT NOT NULL CHECK(role IN ('super', 'normal')),
            is_active INTEGER NOT NULL DEFAULT 1,
            created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
        );

        CREATE TABLE IF NOT EXISTS user_sessions (
            user_id INTEGER PRIMARY KEY,
            session_token TEXT NOT NULL,
            updated_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY(user_id) REFERENCES users(id) ON DELETE CASCADE
        );

        CREATE TABLE IF NOT EXISTS company_settings (
            id INTEGER PRIMARY KEY CHECK(id=1),
            company_name TEXT,
            company_logo_url TEXT,
            company_address TEXT,
            company_phone TEXT,
            tse_no TEXT,
            ysc_types TEXT DEFAULT 'Kuru Kimyevi Toz,CO2,Köpük',
            dashboard_recent_limit INTEGER DEFAULT 10,
            alert_months INTEGER DEFAULT 3
        );

        CREATE TABLE IF NOT EXISTS customers (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            name TEXT NOT NULL,
            contact_name TEXT,
            phone TEXT,
            email TEXT,
            address TEXT,
            note TEXT,
            created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
        );

        CREATE TABLE IF NOT EXISTS labels (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            customer_id INTEGER NOT NULL,
            manufacture_date TEXT,
            serial_no TEXT NOT NULL,
            manufacturer TEXT,
            fill_date TEXT,
            pressure_test_date TEXT,
            next_test_date TEXT,
            next_fill_date TEXT,
            expiry_date TEXT,
            ysc_type TEXT,
            tse_no TEXT,
            qr_token TEXT UNIQUE NOT NULL,
            is_active INTEGER NOT NULL DEFAULT 1,
            print_group_id TEXT,
            created_by INTEGER,
            created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY(customer_id) REFERENCES customers(id) ON DELETE CASCADE,
            FOREIGN KEY(created_by) REFERENCES users(id)
        );

        CREATE TABLE IF NOT EXISTS verification_logs (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            label_id INTEGER NOT NULL,
            verified_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
            ip_address TEXT,
            user_agent TEXT,
            FOREIGN KEY(label_id) REFERENCES labels(id) ON DELETE CASCADE
        );
        """
    )
    db.execute("INSERT OR IGNORE INTO company_settings(id, company_name) VALUES (1, 'Atex Yangın')")
    super_exists = db.execute("SELECT 1 FROM users WHERE role='super'").fetchone()
    if not super_exists:
        db.execute(
            "INSERT INTO users(username,password_hash,role) VALUES (?,?,?)",
            ("super", generate_password_hash("1234"), "super"),
        )
    normal_exists = db.execute("SELECT 1 FROM users WHERE role='normal'").fetchone()
    if not normal_exists:
        db.execute(
            "INSERT INTO users(username,password_hash,role) VALUES (?,?,?)",
            ("operator", generate_password_hash("1234"), "normal"),
        )
    db.commit()
    db.close()


def login_required(f):
    @wraps(f)
    def decorated(*args, **kwargs):
        if "user_id" not in session:
            return redirect(url_for("login"))
        db = get_db()
        user = db.execute("SELECT * FROM users WHERE id=?", (session["user_id"],)).fetchone()
        if not user:
            session.clear()
            return redirect(url_for("login"))
        current = db.execute("SELECT session_token FROM user_sessions WHERE user_id=?", (user["id"],)).fetchone()
        if not current or current["session_token"] != session.get("session_token"):
            session.clear()
            flash("Bu hesap başka bir cihazdan açıldı. Tekrar giriş yapınız.", "warning")
            return redirect(url_for("login"))
        g.user = user
        return f(*args, **kwargs)

    return decorated


def super_required(f):
    @wraps(f)
    def decorated(*args, **kwargs):
        if g.user["role"] != "super":
            flash("Bu işlem için süper kullanıcı yetkisi gerekir.", "danger")
            return redirect(url_for("dashboard"))
        return f(*args, **kwargs)

    return decorated


def parse_date(date_str):
    if not date_str:
        return None
    return datetime.strptime(date_str, "%Y-%m-%d")


def add_years(date_str, years):
    dt = parse_date(date_str)
    if not dt:
        return None
    try:
        return dt.replace(year=dt.year + years).strftime("%Y-%m-%d")
    except ValueError:
        return dt.replace(month=2, day=28, year=dt.year + years).strftime("%Y-%m-%d")


def build_qr_data(token):
    data = f"{request.url_root.rstrip('/')}{url_for('check_label', token=token)}"
    img = qrcode.make(data)
    buffer = io.BytesIO()
    img.save(buffer, format="PNG")
    return "data:image/png;base64," + base64.b64encode(buffer.getvalue()).decode()


@app.route("/")
def root_redirect():
    return redirect(url_for("login"))


@app.route("/login", methods=["GET", "POST"])
def login():
    db = get_db()
    settings = db.execute("SELECT * FROM company_settings WHERE id=1").fetchone()
    if request.method == "POST":
        username = request.form.get("username", "").strip()
        password = request.form.get("password", "")
        user = db.execute("SELECT * FROM users WHERE username=? AND is_active=1", (username,)).fetchone()
        if user and check_password_hash(user["password_hash"], password):
            token = str(uuid.uuid4())
            db.execute(
                "INSERT INTO user_sessions(user_id,session_token,updated_at) VALUES (?,?,CURRENT_TIMESTAMP) "
                "ON CONFLICT(user_id) DO UPDATE SET session_token=excluded.session_token, updated_at=CURRENT_TIMESTAMP",
                (user["id"], token),
            )
            db.commit()
            session.clear()
            session["user_id"] = user["id"]
            session["session_token"] = token
            return redirect(url_for("dashboard"))
        flash("Kullanıcı adı veya şifre hatalı.", "danger")
    return render_template("login.html", settings=settings)


@app.route("/logout")
@login_required
def logout():
    db = get_db()
    db.execute("DELETE FROM user_sessions WHERE user_id=?", (g.user["id"],))
    db.commit()
    session.clear()
    return redirect(url_for("login"))


@app.route("/dashboard")
@login_required
def dashboard():
    db = get_db()
    settings = db.execute("SELECT * FROM company_settings WHERE id=1").fetchone()
    limit = max(settings["dashboard_recent_limit"] or 10, 1)
    months = max(settings["alert_months"] or 3, 1)
    recent = db.execute(
        """
        SELECT vl.verified_at, vl.ip_address, l.serial_no, c.name AS customer_name
        FROM verification_logs vl
        JOIN labels l ON l.id = vl.label_id
        JOIN customers c ON c.id = l.customer_id
        ORDER BY vl.verified_at DESC
        LIMIT ?
        """,
        (limit,),
    ).fetchall()
    upcoming = db.execute(
        """
        SELECT c.name AS customer_name, l.serial_no, l.next_fill_date, l.next_test_date, l.expiry_date
        FROM labels l
        JOIN customers c ON c.id = l.customer_id
        WHERE l.is_active=1 AND (
            date(l.next_fill_date) <= date('now', '+' || ? || ' months') OR
            date(l.next_test_date) <= date('now', '+' || ? || ' months') OR
            date(l.expiry_date) <= date('now', '+' || ? || ' months')
        )
        ORDER BY l.next_fill_date ASC
        LIMIT 50
        """,
        (months, months, months),
    ).fetchall()
    counts = {
        "customers": db.execute("SELECT COUNT(*) n FROM customers").fetchone()["n"],
        "labels": db.execute("SELECT COUNT(*) n FROM labels").fetchone()["n"],
        "active_labels": db.execute("SELECT COUNT(*) n FROM labels WHERE is_active=1").fetchone()["n"],
    }
    return render_template("dashboard.html", settings=settings, recent=recent, upcoming=upcoming, counts=counts)


@app.route("/customers", methods=["GET", "POST"])
@login_required
def customers():
    db = get_db()
    if request.method == "POST":
        db.execute(
            "INSERT INTO customers(name,contact_name,phone,email,address,note) VALUES (?,?,?,?,?,?)",
            (
                request.form.get("name"),
                request.form.get("contact_name"),
                request.form.get("phone"),
                request.form.get("email"),
                request.form.get("address"),
                request.form.get("note"),
            ),
        )
        db.commit()
        flash("Müşteri eklendi.", "success")
    rows = db.execute("SELECT * FROM customers ORDER BY id DESC").fetchall()
    return render_template("customers.html", customers=rows)


@app.route("/customers/<int:customer_id>", methods=["GET", "POST"])
@login_required
def customer_detail(customer_id):
    db = get_db()
    customer = db.execute("SELECT * FROM customers WHERE id=?", (customer_id,)).fetchone()
    if not customer:
        flash("Müşteri bulunamadı.", "danger")
        return redirect(url_for("customers"))

    if request.method == "POST":
        db.execute(
            "UPDATE customers SET name=?,contact_name=?,phone=?,email=?,address=?,note=? WHERE id=?",
            (
                request.form.get("name"),
                request.form.get("contact_name"),
                request.form.get("phone"),
                request.form.get("email"),
                request.form.get("address"),
                request.form.get("note"),
                customer_id,
            ),
        )
        db.commit()
        flash("Müşteri güncellendi.", "success")
        return redirect(url_for("customer_detail", customer_id=customer_id))

    labels = db.execute(
        """
        SELECT l.*, (SELECT COUNT(*) FROM verification_logs vl WHERE vl.label_id=l.id) AS verify_count
        FROM labels l
        WHERE l.customer_id=?
        ORDER BY l.created_at DESC
        """,
        (customer_id,),
    ).fetchall()
    return render_template("customer_detail.html", customer=customer, labels=labels)


@app.route("/labels/<int:label_id>/toggle", methods=["POST"])
@login_required
def toggle_label(label_id):
    db = get_db()
    db.execute("UPDATE labels SET is_active = CASE WHEN is_active=1 THEN 0 ELSE 1 END WHERE id=?", (label_id,))
    db.commit()
    flash("Etiket durumu güncellendi.", "success")
    return redirect(request.referrer or url_for("dashboard"))


@app.route("/labels/<int:label_id>/delete", methods=["POST"])
@login_required
def delete_label(label_id):
    db = get_db()
    db.execute("DELETE FROM labels WHERE id=?", (label_id,))
    db.commit()
    flash("Etiket silindi.", "success")
    return redirect(request.referrer or url_for("dashboard"))


@app.route("/labels", methods=["GET", "POST"])
@login_required
def labels():
    db = get_db()
    settings = db.execute("SELECT * FROM company_settings WHERE id=1").fetchone()
    customers_rows = db.execute("SELECT id,name FROM customers ORDER BY name").fetchall()
    ysc_types = [x.strip() for x in (settings["ysc_types"] or "").split(",") if x.strip()]

    if request.method == "POST":
        customer_id = int(request.form.get("customer_id"))
        quantity = max(1, int(request.form.get("quantity", 1)))
        serial_base = request.form.get("serial_no", "").strip()
        print_group_id = str(uuid.uuid4())
        for i in range(quantity):
            serial = serial_base
            if quantity > 1:
                if serial_base.isdigit():
                    serial = str(int(serial_base) + i)
                else:
                    serial = f"{serial_base}-{i+1}"
            fill_date = request.form.get("fill_date")
            pressure_date = request.form.get("pressure_test_date")
            db.execute(
                """
                INSERT INTO labels(
                    customer_id, manufacture_date, serial_no, manufacturer,
                    fill_date, pressure_test_date, next_test_date,
                    next_fill_date, expiry_date, ysc_type, tse_no,
                    qr_token, print_group_id, created_by
                ) VALUES(?,?,?,?,?,?,?,?,?,?,?,?,?,?)
                """,
                (
                    customer_id,
                    request.form.get("manufacture_date"),
                    serial,
                    request.form.get("manufacturer"),
                    fill_date,
                    pressure_date,
                    add_years(pressure_date, 1),
                    add_years(fill_date, 1),
                    add_years(fill_date, 4),
                    request.form.get("ysc_type"),
                    settings["tse_no"],
                    str(uuid.uuid4()),
                    print_group_id,
                    g.user["id"],
                ),
            )
        db.commit()
        flash(f"{quantity} adet etiket üretildi.", "success")
        return redirect(url_for("labels", customer_id=customer_id))

    selected_customer = request.args.get("customer_id", type=int)
    history = []
    if selected_customer:
        history = db.execute(
            "SELECT * FROM labels WHERE customer_id=? ORDER BY created_at DESC LIMIT 100", (selected_customer,)
        ).fetchall()

    return render_template(
        "labels.html",
        customers=customers_rows,
        settings=settings,
        ysc_types=ysc_types,
        selected_customer=selected_customer,
        history=history,
        today=datetime.now().strftime("%Y-%m-%d"),
    )


@app.route("/settings", methods=["GET", "POST"])
@login_required
def settings():
    db = get_db()
    if request.method == "POST":
        db.execute(
            """
            UPDATE company_settings
            SET company_name=?, company_logo_url=?, company_address=?, company_phone=?,
                tse_no=?, ysc_types=?, dashboard_recent_limit=?, alert_months=?
            WHERE id=1
            """,
            (
                request.form.get("company_name"),
                request.form.get("company_logo_url"),
                request.form.get("company_address"),
                request.form.get("company_phone"),
                request.form.get("tse_no"),
                request.form.get("ysc_types"),
                max(request.form.get("dashboard_recent_limit", type=int) or 10, 1),
                max(request.form.get("alert_months", type=int) or 3, 1),
            ),
        )
        db.commit()
        flash("Firma ayarları güncellendi.", "success")
    settings_row = db.execute("SELECT * FROM company_settings WHERE id=1").fetchone()
    users = db.execute("SELECT id,username,role,is_active,created_at FROM users ORDER BY id").fetchall()
    return render_template("settings.html", settings=settings_row, users=users)


@app.route("/settings/users", methods=["POST"])
@login_required
@super_required
def create_user():
    db = get_db()
    db.execute(
        "INSERT INTO users(username,password_hash,role) VALUES(?,?,?)",
        (
            request.form.get("username"),
            generate_password_hash(request.form.get("password", "1234")),
            request.form.get("role", "normal"),
        ),
    )
    db.commit()
    flash("Kullanıcı eklendi.", "success")
    return redirect(url_for("settings"))


@app.route("/settings/users/<int:user_id>/password", methods=["POST"])
@login_required
def update_password(user_id):
    db = get_db()
    target = db.execute("SELECT * FROM users WHERE id=?", (user_id,)).fetchone()
    if not target:
        abort(404)
    if g.user["role"] != "super" and g.user["id"] != user_id:
        flash("Bu şifreyi değiştirme yetkiniz yok.", "danger")
        return redirect(url_for("settings"))
    if g.user["role"] != "super" and target["role"] == "super":
        flash("Süper kullanıcı şifresi yalnızca süper kullanıcı tarafından değiştirilebilir.", "danger")
        return redirect(url_for("settings"))
    db.execute(
        "UPDATE users SET password_hash=? WHERE id=?",
        (generate_password_hash(request.form.get("password")), user_id),
    )
    db.commit()
    flash("Şifre güncellendi.", "success")
    return redirect(url_for("settings"))


@app.route("/check/<token>")
def check_label(token):
    db = get_db()
    row = db.execute(
        """
        SELECT l.*, c.name customer_name, c.address customer_address, c.phone customer_phone
        FROM labels l
        JOIN customers c ON c.id=l.customer_id
        WHERE l.qr_token=?
        """,
        (token,),
    ).fetchone()
    if not row:
        return render_template("check.html", label=None)
    db.execute(
        "INSERT INTO verification_logs(label_id, ip_address, user_agent) VALUES(?,?,?)",
        (row["id"], request.headers.get("X-Forwarded-For", request.remote_addr), request.user_agent.string[:250]),
    )
    db.commit()
    return render_template("check.html", label=row)


@app.context_processor
def inject_helpers():
    return {"build_qr_data": build_qr_data, "base_path": BASE_PATH}


@app.route("/admin/reset-demo", methods=["POST"])
@login_required
@super_required
def reset_demo_data():
    db = get_db()
    db.executescript(
        """
        DELETE FROM verification_logs;
        DELETE FROM labels;
        DELETE FROM customers;
        """
    )
    db.commit()
    flash("Test verileri temizlendi. Kullanıcılar ve ayarlar korundu.", "success")
    return redirect(url_for("dashboard"))


init_db()

if __name__ == "__main__":
    app.run(host="0.0.0.0", port=5000, debug=True)
