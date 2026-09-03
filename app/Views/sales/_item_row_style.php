<style>
	#saleForm .select2-container--default .select2-selection--single {
		background-color: #fff;
		border: 1px solid #aaa;
		border-radius: 4px;
		height: 30px !important;
	}
	#saleForm .select2-container--default .select2-selection--single .select2-selection__rendered {
		line-height: 30px !important;
		font-size: 0.78rem;
		white-space: nowrap;
		overflow: hidden;
		text-overflow: ellipsis;
	}
	#saleForm .select2-container--default .select2-selection--single .select2-selection__arrow {
		height: 28px !important;
	}

	/* The app-wide Select2 sweep in layouts/main.php also wraps Stock Source / GST on rows
	   that already exist at DOMContentLoaded (e.g. Sales Edit's prefilled rows), with
	   allowClear:true. Hiding the clear "x" reclaims width these compact fixed-width
	   columns need — these are required fields, clearing them to blank isn't a valid state
	   anyway. */
	#saleForm .pr-field-source .select2-selection__clear,
	#saleForm .pr-field-gstapp .select2-selection__clear {
		display: none;
	}

	/* Release 1.8B.4: single-line compact item row (max ~90px height, no second row). */
	#saleForm .pr-item-row {
		padding: 4px 8px;
		margin-bottom: 6px;
	}
	#saleForm .pr-item-row .remove-item {
		position: static;
		font-size: 0.95rem;
		background: none;
		border: none;
		color: #dc3545;
		cursor: pointer;
		width: 30px;
		height: 30px;
		display: flex;
		align-items: center;
		justify-content: center;
	}

	#saleForm .pr-row1 {
		display: flex;
		flex-wrap: nowrap;
		gap: 6px;
		align-items: flex-end;
	}

	#saleForm .pr-field .form-section { margin-bottom: 0; }
	#saleForm .pr-field .form-label {
		font-size: 0.6rem;
		font-weight: 600;
		text-transform: uppercase;
		letter-spacing: 0.02em;
		margin-bottom: 0;
		color: var(--text-muted);
		white-space: nowrap;
		overflow: hidden;
		text-overflow: ellipsis;
		line-height: 1.15;
	}
	#saleForm .pr-field .form-control,
	#saleForm .pr-field .form-control-plaintext {
		height: 30px;
		font-size: 0.76rem;
		padding: 0 6px;
	}
	#saleForm .pr-field .form-control-plaintext {
		display: flex;
		align-items: center;
		color: var(--text-dark);
	}

	#saleForm .pr-field-source  { flex: 0 0 120px; }
	#saleForm .pr-field-product { flex: 0 0 230px; min-width: 0; }
	#saleForm .pr-field-avail   { flex: 0 0 80px; }
	#saleForm .pr-field-qty     { flex: 0 0 65px; }
	#saleForm .pr-field-price   { flex: 0 0 90px; }
	#saleForm .pr-field-gstpct  { flex: 0 0 60px; }
	#saleForm .pr-field-gstapp  { flex: 0 0 85px; }
	#saleForm .pr-field-total   { flex: 0 0 110px; }
	#saleForm .pr-field-remove  { flex: 0 0 32px; }

	#saleForm .pr-field-total .line-total {
		font-weight: 700;
		font-size: 0.88rem;
		text-align: right;
		color: var(--primary);
		border-color: transparent;
		background: transparent;
		padding: 0;
	}

	#saleForm .pr-avail-inline {
		display: flex;
		align-items: center;
		gap: 4px;
	}
	#saleForm .pr-avail-inline .available-stock {
		width: 56px;
		flex: 0 0 56px;
		text-align: right;
		padding: 0 4px;
		overflow: hidden;
		text-overflow: ellipsis;
	}

	/* Compact dot indicator (color-only) so the status badge fits beside the 80px
	   Available Qty field without needing a second row or extra width. */
	#saleForm .allocation-badge {
		display: inline-block;
		flex: 0 0 9px;
		width: 9px;
		height: 9px;
		padding: 0;
		border-radius: 50%;
		font-size: 0;
		line-height: 0;
		overflow: hidden;
	}
	#saleForm .allocation-badge.badge-in-stock      { background: #22c55e; }
	#saleForm .allocation-badge.badge-low-stock     { background: #f59e0b; }
	#saleForm .allocation-badge.badge-out-stock     { background: #ef4444; }
	#saleForm .allocation-badge.badge-general-stock { background: #3b82f6; }

	#saleForm .pr-totals-strip {
		display: flex;
		flex-wrap: wrap;
		align-items: center;
		justify-content: flex-end;
		gap: 10px;
		margin-top: 10px;
		padding: 10px 14px;
		background: #f8fafc;
		border: 1px solid var(--border-color);
		border-radius: var(--border-radius);
		position: sticky;
		bottom: 0;
	}
	#saleForm .pr-totals-strip .pr-total-item {
		display: flex;
		align-items: baseline;
		gap: 6px;
		font-size: 0.85rem;
		color: var(--text-muted);
	}
	#saleForm .pr-totals-strip .pr-total-item .total-value {
		font-size: 0.95rem;
		font-weight: 600;
		color: var(--text-dark);
	}
	#saleForm .pr-totals-strip .pr-total-sep {
		color: var(--text-muted);
		font-size: 0.9rem;
	}
	#saleForm .pr-totals-strip .pr-total-divider {
		width: 1px;
		align-self: stretch;
		background: var(--border-color);
		margin: 0 4px;
	}
	#saleForm .pr-totals-strip .pr-grand-total {
		display: flex;
		align-items: baseline;
		gap: 8px;
		padding: 4px 14px;
		background: var(--primary);
		border-radius: var(--border-radius);
	}
	#saleForm .pr-totals-strip .pr-grand-total .total-label {
		color: #dbeafe;
		font-size: 0.78rem;
		font-weight: 600;
		text-transform: uppercase;
		letter-spacing: 0.03em;
	}
	#saleForm .pr-totals-strip .pr-grand-total .total-value {
		color: #fff;
		font-size: 1.15rem;
		font-weight: 700;
	}

	@media (max-width: 991px) {
		#saleForm .pr-row1 { flex-wrap: wrap; }
		#saleForm .pr-field-product { flex: 1 1 100%; order: -1; }
		#saleForm .pr-field-source  { flex: 1 1 calc(50% - 6px); }
		#saleForm .pr-field-qty,
		#saleForm .pr-field-price,
		#saleForm .pr-field-gstpct,
		#saleForm .pr-field-gstapp,
		#saleForm .pr-field-avail,
		#saleForm .pr-field-total {
			flex: 1 1 calc(33.33% - 6px);
		}
		#saleForm .pr-field-remove { margin-left: auto; }
		#saleForm .pr-totals-strip { justify-content: space-between; }
	}

	@media (max-width: 575px) {
		#saleForm .pr-field-source,
		#saleForm .pr-field-qty,
		#saleForm .pr-field-price,
		#saleForm .pr-field-gstpct,
		#saleForm .pr-field-gstapp,
		#saleForm .pr-field-avail,
		#saleForm .pr-field-total {
			flex: 1 1 calc(50% - 6px);
		}
		#saleForm .pr-totals-strip {
			flex-direction: column;
			align-items: stretch;
		}
		#saleForm .pr-totals-strip .pr-grand-total { justify-content: space-between; }
	}
</style>
