<?php
require_once 'config.php';
checkAuth();
require_roles(['admin', 'caissier']); // Ventes / factures : admin + caissier uniquement

$theme = getCurrentTheme();

// Récupérer les produits disponibles
$products = $db->query("SELECT p.id, p.nom, p.prix_vente, s.quantite 
                        FROM produits p 
                        JOIN stock s ON p.id = s.produit_id
                        WHERE s.quantite > 0
                        ORDER BY p.nom")->fetchAll();

// Traitement du formulaire
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $client_nom = $_POST['client_nom'];
    $client_contact = $_POST['client_contact'];
    $items = $_POST['items'];
    $remise = floatval($_POST['remise']);
    $mode_paiement = $_POST['mode_paiement'];
    $notes = $_POST['notes'];

    try {
        $db->beginTransaction();

        // 1. Insérer la facture avec des montants initiaux
        $stmt = $db->prepare("INSERT INTO factures (client_nom, client_contact, montant_total, montant_final, remise, mode_paiement, notes, utilisateur_id) 
                             VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([$client_nom, $client_contact, 0.00, 0.00, $remise, $mode_paiement, $notes, $_SESSION['user_id']]);
        $invoice_id = $db->lastInsertId();

        // 2. Insérer les articles et mettre à jour le stock
        $total = 0;
        foreach ($items as $item) {
            $product_id = $item['product_id'];
            $quantite = $item['quantite'];
            $prix_unitaire = $item['prix_unitaire'];

            $stmt = $db->prepare("INSERT INTO facture_items (facture_id, produit_id, quantite, prix_unitaire) 
                                 VALUES (?, ?, ?, ?)");
            $stmt->execute([$invoice_id, $product_id, $quantite, $prix_unitaire]);

            // Mettre à jour le stock
            $stmt = $db->prepare("UPDATE stock SET quantite = quantite - ? WHERE produit_id = ?");
            $stmt->execute([$quantite, $product_id]);

            // Ajouter un mouvement de sortie
            $stmt = $db->prepare("INSERT INTO mouvements (produit_id, utilisateur_id, type, quantite, motif) VALUES (?, ?, 'sortie', ?, 'Facture #$invoice_id')");
            $stmt->execute([$product_id, $_SESSION['user_id'], $quantite]);

            $total += $quantite * $prix_unitaire;
        }

        // 3. Mettre à jour le total de la facture
        $total_apres_remise = $total - $remise;
        $stmt = $db->prepare("UPDATE factures SET montant_total = ?, montant_final = ? WHERE id = ?");
        $stmt->execute([$total, $total_apres_remise, $invoice_id]);

        $db->commit();

        flash_set('Facture créée avec succès.', 'success', 'invoices.php');
        header("Location: invoices.php");
        exit();
    } catch (PDOException $e) {
        $db->rollBack();
        $error = "Erreur lors de la création de la facture: " . $e->getMessage();
    }
}
?>

<!DOCTYPE html>
<html lang="fr" data-bs-theme="<?= $theme ?>">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Créer une facture - StockMaster</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="css/style.css">
    <style>
        .product-select {
            max-height: 200px;
            overflow-y: auto;
        }

        .invoice-item {
            background-color: <?= $theme == 'dark' ? '#343a40' : '#f8f9fa' ?>;
            border-radius: 5px;
            padding: 10px;
            margin-bottom: 10px;
        }

        .total-section {
            background-color: <?= $theme == 'dark' ? '#495057' : '#e9ecef' ?>;
            padding: 15px;
            border-radius: 5px;
        }
    </style>
</head>

<body class="<?= $theme == 'dark' ? 'bg-dark text-light' : 'bg-light' ?>">
    <div class="wrapper">
        <?php include_once('includes/sidebar.php'); ?>

        <div id="content">
            <?php include_once('includes/navbar.php'); ?>

            <div class="container-fluid px-4">
                <div class="row my-4">
                    <div class="col-12">
                        <h2 class="mb-4"><i class="fas fa-file-invoice me-2"></i> Créer une facture</h2>
                        <hr>
                    </div>
                </div>

                <?php if (isset($error)): ?>
                    <div class="alert alert-danger"><?= $error ?></div>
                <?php endif; ?>

                <form method="POST" id="invoiceForm">
                    <div class="row mb-4">
                        <div class="col-md-6">
                            <div class="card shadow mb-4">
                                <div class="card-header bg-primary text-white">
                                    <h5 class="mb-0"><i class="fas fa-user me-2"></i> Client</h5>
                                </div>
                                <div class="card-body">
                                    <div class="mb-3">
                                        <label for="client_nom" class="form-label">Nom du client*</label>
                                        <input type="text" class="form-control" id="client_nom" name="client_nom" required>
                                    </div>
                                    <div class="mb-3">
                                        <label for="client_contact" class="form-label">Contact</label>
                                        <input type="text" class="form-control" id="client_contact" name="client_contact">
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="card shadow mb-4">
                                <div class="card-header bg-primary text-white">
                                    <h5 class="mb-0"><i class="fas fa-cog me-2"></i> Paramètres</h5>
                                </div>
                                <div class="card-body">
                                    <div class="mb-3">
                                        <label for="mode_paiement" class="form-label">Mode de paiement*</label>
                                        <select class="form-select" id="mode_paiement" name="mode_paiement" required>
                                            <option value="cash">Espèces</option>
                                            <option value="card">Carte bancaire</option>
                                            <option value="transfer">Virement</option>
                                            <option value="check">Chèque</option>
                                        </select>
                                    </div>
                                    <div class="mb-3">
                                        <label for="remise" class="form-label">Remise (FCFA)</label>
                                        <input type="number" class="form-control" id="remise" name="remise" min="0" value="0" step="0.01">
                                    </div>
                                    <div class="mb-3">
                                        <label for="notes" class="form-label">Notes</label>
                                        <textarea class="form-control" id="notes" name="notes" rows="2"></textarea>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="card shadow mb-4">
                        <div class="card-header bg-primary text-white d-flex justify-content-between align-items-center">
                            <h5 class="mb-0"><i class="fas fa-boxes me-2"></i> Articles</h5>
                            <button type="button" class="btn btn-sm btn-light" id="addItemBtn">
                                <i class="fas fa-plus me-1"></i> Ajouter
                            </button>
                        </div>
                        <div class="card-body">
                            <div id="itemsContainer">
                                <!-- Les articles seront ajoutés ici dynamiquement -->
                            </div>
                        </div>
                    </div>

                    <div class="card shadow mb-4">
                        <div class="card-body total-section">
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label class="form-label">Total: <span id="totalAmount">0</span> FCFA</label>
                                    </div>
                                    <div class="mb-3">
                                        <label class="form-label">Remise: <span id="discountAmount">0</span> FCFA</label>
                                    </div>
                                    <div class="mb-3">
                                        <label class="form-label">Total à payer: <span id="finalAmount">0</span> FCFA</label>
                                    </div>
                                </div>
                                <div class="col-md-6 text-end">
                                    <button type="submit" class="btn btn-primary btn-lg">
                                        <i class="fas fa-save me-2"></i> Enregistrer la facture
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Template pour un nouvel article (caché) -->
    <div id="itemTemplate" class="d-none">
        <div class="invoice-item">
            <div class="row g-3">
                <div class="col-md-5">
                    <select class="form-select product-select" name="items[INDEX][product_id]" required>
                        <option value="">Sélectionner un produit</option>
                        <?php foreach ($products as $product): ?>
                            <option value="<?= $product['id'] ?>"
                                data-price="<?= $product['prix_vente'] ?>"
                                data-stock="<?= $product['quantite'] ?>">
                                <?= htmlspecialchars($product['nom']) ?> (<?= $product['prix_vente'] ?> FCFA - Stock: <?= $product['quantite'] ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-2">
                    <input type="number" class="form-control quantity" name="items[INDEX][quantite]" min="1" value="1" required>
                </div>
                <div class="col-md-3">
                    <input type="number" class="form-control price" name="items[INDEX][prix_unitaire]" step="0.01" min="0" required>
                </div>
                <div class="col-md-2">
                    <button type="button" class="btn btn-danger btn-sm remove-item">
                        <i class="fas fa-trash"></i>
                    </button>
                </div>
            </div>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        $(document).ready(function() {
            let itemCount = 0;

            // Ajouter un nouvel article
            $('#addItemBtn').click(function() {
                const newItem = $('#itemTemplate').html().replace(/INDEX/g, itemCount);
                $('#itemsContainer').append(newItem);
                itemCount++;
                updateTotals();
            });

            // Supprimer un article
            $(document).on('click', '.remove-item', function() {
                $(this).closest('.invoice-item').remove();
                updateTotals();
            });

            // Mettre à jour le prix quand le produit est sélectionné
            $(document).on('change', '.product-select', function() {
                const selectedOption = $(this).find('option:selected');
                const price = selectedOption.data('price');
                const stock = selectedOption.data('stock');

                $(this).closest('.invoice-item').find('.price').val(price);
                $(this).closest('.invoice-item').find('.quantity').attr('max', stock);
                updateTotals();
            });

            // Mettre à jour les totaux quand la quantité ou le prix change
            $(document).on('input', '.quantity, .price, #remise', function() {
                updateTotals();
            });

            // Fonction pour calculer les totaux
            function updateTotals() {
                let total = 0;

                $('.invoice-item').each(function() {
                    const quantity = parseFloat($(this).find('.quantity').val()) || 0;
                    const price = parseFloat($(this).find('.price').val()) || 0;
                    total += quantity * price;
                });

                const discount = parseFloat($('#remise').val()) || 0;
                const finalTotal = total - discount;

                $('#totalAmount').text(total.toFixed(2));
                $('#discountAmount').text(discount.toFixed(2));
                $('#finalAmount').text(finalTotal.toFixed(2));
            }

            // Ajouter un article par défaut au chargement
            $('#addItemBtn').click();
        });
    </script>
</body>

</html>