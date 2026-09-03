<?= $this->extend('layouts/main') ?>
<?= $this->section('content') ?>

<style>
	
	/* REPORT CARDS */
.report-card {
    text-align: center;
    padding: 28px;
    border-radius: 16px;
    cursor: pointer;
    position: relative;
    overflow: hidden;
    background: linear-gradient(145deg, #ffffff, #f1f5f9);
    transition: all 0.25s ease;
    box-shadow:
        6px 6px 14px rgba(0,0,0,0.06),
        -4px -4px 10px rgba(255,255,255,0.9);
}

/* ICON */
.report-icon {
    width: 60px;
    height: 60px;
    margin: 0 auto 12px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;

    font-size: 1.8rem;

    /* GLASS EFFECT */
    background: rgba(255,255,255,0.35);
    backdrop-filter: blur(6px);
    box-shadow:
        inset 0 2px 4px rgba(255,255,255,0.6),
        0 4px 10px rgba(0,0,0,0.08);

    transition: transform 0.25s ease, box-shadow 0.25s ease;
}
	
.report-card:hover .report-icon {
    transform: scale(1.12) rotate(6deg);
    box-shadow:
        inset 0 3px 6px rgba(255,255,255,0.7),
        0 6px 16px rgba(0,0,0,0.12);
}
	
/* RIPPLE */
.report-card {
    position: relative;
    overflow: hidden;
}

.ripple {
    position: absolute;
    border-radius: 50%;
    transform: scale(0);
    background: rgba(255,255,255,0.6);
    animation: rippleAnim 0.5s linear;
    pointer-events: none;
}

@keyframes rippleAnim {
    to {
        transform: scale(4);
        opacity: 0;
    }
}


/* TITLE */
.report-title {
    font-weight: 700;
    font-size: 1rem;
    color: #1f2937;
}

/* SUBTEXT */
.report-sub {
    font-size: 0.8rem;
    color: #1e293b;
    margin-top: 6px;
}

.report-icon i {
    filter: drop-shadow(0 1px 2px rgba(0,0,0,0.2));
}
	
/* HOVER (3D PRESS EFFECT) */
.report-card:hover {
    box-shadow:
        inset 0 0 10px rgba(255,255,255,0.5),
        4px 4px 12px rgba(0,0,0,0.08);
}
	
/* TEXT DEPTH */
.report-title {
    text-shadow: 0 1px 0 rgba(255,255,255,0.6);
}

/* LIGHT SWEEP */
.report-card::after {
    content: '';
    position: absolute;
    top: 0;
    left: -120%;
    width: 60%;
    height: 100%;
    background: linear-gradient(
        120deg,
        transparent,
        rgba(255,255,255,0.4),
        transparent
    );
    transform: skewX(-20deg);
}

.report-card:hover::after {
    animation: lightSweep 0.7s ease forwards;
}
	
/* PARALLAX LAYERS */
.report-icon,
.report-title,
.report-sub {
    transition: transform 0.2s ease;
}
	
/* COLOR SHIFT OVERLAY */
.report-card::before {
    content: '';
    position: absolute;
    inset: 0;
    background: radial-gradient(circle at var(--x) var(--y),
        rgba(255,255,255,0.35),
        transparent 60%);
    opacity: 0;
    transition: opacity 0.2s ease;
}
	
.report-card:hover::before {
    opacity: 1;
}

/* COLOR VARIANTS */
.report-blue {
    background: linear-gradient(145deg, #7fa6d9, #bfdbfe);
    border-top: 4px solid #2563eb;
}
.report-green {
    background: linear-gradient(145deg, #6fbf8f, #bbf7d0);
    border-top: 4px solid #16a34a;
}
.report-orange {
    background: linear-gradient(145deg, #e6b47c, #fed7aa);
    border-top: 4px solid #d97706;
}
.report-purple {
    background: linear-gradient(145deg, #9b8bd6, #ddd6fe);
    border-top: 4px solid #7c3aed;
}
.report-cyan {
    background: linear-gradient(145deg, #4aa3a8, #a5f3fc);
    border-top: 4px solid #0891b2;
}
	
</style>

<div class="page-title">
    <span><i class="bi bi-bar-chart-line me-2"></i>Reports</span>
</div>

<div class="row g-3">
    <div class="col-md-4">
        <a href="<?= base_url('reports/profit-loss') ?>" style="text-decoration:none">
            <div class="report-card report-blue">
                <div class="report-icon" style="color:#2563eb"><i class="bi bi-graph-up-arrow"></i></div>
                <div class="report-title">Profit & Loss Report</div>
                <div class="report-sub">Revenue vs Expenses by project</div>
            </div>
        </a>
    </div>
    <div class="col-md-4">
        <a href="<?= base_url('reports/stock') ?>" style="text-decoration:none">
			<div class="report-card report-green">
				<div class="report-icon" style="color:#16a34a"><i class="bi bi-boxes"></i></div>
				<div class="report-title">Stock Summary</div>
				<div class="report-sub">General & project stock by product</div>
			</div>
				</a>
	</div>
    <div class="col-md-4">
        <a href="<?= base_url('reports/ledger') ?>" style="text-decoration:none">
            <div class="report-card report-orange">
                <div class="report-icon" style="color:#d97706"><i class="bi bi-journal-text"></i></div>
                <div class="report-title">Stock Ledger</div>
                <div class="report-sub">Full IN/OUT stock movement log</div>
            </div>
        </a>
    </div>
    <div class="col-md-4">
        <a href="<?= base_url('reports/sales') ?>" style="text-decoration:none">
            <div class="report-card report-purple">
                <div class="report-icon" style="color:#7c3aed"><i class="bi bi-receipt-cutoff"></i></div>
                <div class="report-title">Sales Report</div>
                <div class="report-sub">All sales with payment status</div>
            </div>
        </a>
    </div>
    <div class="col-md-4">
        <a href="<?= base_url('reports/purchases') ?>" style="text-decoration:none">
            <div class="report-card report-cyan">
                <div class="report-icon" style="color:#0891b2"><i class="bi bi-cart-fill"></i></div>
                <div class="report-title">Purchases Report</div>
                <div class="report-sub">All purchase history by supplier</div>
            </div>
        </a>
    </div>
</div>

<script>
document.querySelectorAll('.report-card').forEach(card => {
    card.addEventListener('click', function(e) {
        const circle = document.createElement('span');
        circle.classList.add('ripple');

        const rect = this.getBoundingClientRect();
        const size = Math.max(rect.width, rect.height);

        circle.style.width = circle.style.height = size + 'px';
        circle.style.left = (e.clientX - rect.left - size/2) + 'px';
        circle.style.top = (e.clientY - rect.top - size/2) + 'px';

        this.appendChild(circle);

        setTimeout(() => circle.remove(), 500);
    });
});
</script>

<script>
// PARALLAX EFFECT
document.querySelectorAll('.report-card').forEach(card => {

    const icon = card.querySelector('.report-icon');
    const title = card.querySelector('.report-title');
    const sub = card.querySelector('.report-sub');

    card.addEventListener('mousemove', (e) => {
        const rect = card.getBoundingClientRect();

        const x = (e.clientX - rect.left - rect.width/2) / 20;
        const y = (e.clientY - rect.top - rect.height/2) / 20;

        icon.style.transform = `translate(${x*2}px, ${y*2}px)`;
        title.style.transform = `translate(${x}px, ${y}px)`;
        sub.style.transform = `translate(${x*0.5}px, ${y*0.5}px)`;
    });

    card.addEventListener('mouseleave', () => {
        icon.style.transform = '';
        title.style.transform = '';
        sub.style.transform = '';
    });

});
</script>

<script>
// COLOR SHIFT BASED ON CURSOR
document.querySelectorAll('.report-card').forEach(card => {

    card.addEventListener('mousemove', (e) => {
        const rect = card.getBoundingClientRect();
        const x = e.clientX - rect.left;
        const y = e.clientY - rect.top;

        card.style.setProperty('--x', x + 'px');
        card.style.setProperty('--y', y + 'px');

        card.querySelector(':scope').style;
    });

});
</script>

<?= $this->endSection() ?>

