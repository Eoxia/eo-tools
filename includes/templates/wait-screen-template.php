<?php
/**
 * Template for the post-registration wait screen (games or timer)
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$accent_color = $page_settings['accent_color'] ?? '#0066FF';
?>
<div id="eo-lp-wait-screen" style="text-align: center; padding: 20px 0;">
	
	<div id="eo-lp-wait-content">
		<?php if ( 'timer' === $success_action ) : ?>
			
			<div class="eo-lp-timer-container" style="margin: 30px auto; width: 100px; height: 100px; position: relative;">
				<svg viewBox="0 0 100 100" style="transform: rotate(-90deg); width: 100%; height: 100%;">
					<circle cx="50" cy="50" r="45" fill="none" stroke="#e2e8f0" stroke-width="8"></circle>
					<circle cx="50" cy="50" r="45" fill="none" stroke="<?php echo esc_attr( $accent_color ); ?>" stroke-width="8" stroke-dasharray="283" stroke-dashoffset="0" style="transition: stroke-dashoffset 1s linear;" id="eo-lp-timer-circle"></circle>
				</svg>
				<div id="eo-lp-timer-text" style="position: absolute; top: 0; left: 0; width: 100%; height: 100%; display: flex; align-items: center; justify-content: center; font-size: 24px; font-weight: bold; color: <?php echo esc_attr( $accent_color ); ?>;">60</div>
			</div>
			<p style="font-size: 16px; margin-bottom: 20px;"><?php esc_html_e( 'L\'e-mail est en route...', 'eo-tools' ); ?></p>
			
			<script>
				document.addEventListener('DOMContentLoaded', function() {
					var timeLeft = 60;
					var timerCircle = document.getElementById('eo-lp-timer-circle');
					var timerText = document.getElementById('eo-lp-timer-text');
					var totalDash = 283;
					
					var interval = setInterval(function() {
						timeLeft--;
						if (timeLeft < 0) {
							clearInterval(interval);
							timerText.innerHTML = '<span class="dashicons dashicons-yes" style="font-size: 32px; width: 32px; height: 32px; line-height: 32px;"></span>';
							return;
						}
						timerText.innerText = timeLeft;
						var offset = totalDash - (timeLeft / 60) * totalDash;
						timerCircle.style.strokeDashoffset = offset;
					}, 1000);
				});
			</script>

		<?php elseif ( 'tictactoe' === $success_action ) : ?>
			
			<p style="font-size: 15px; margin-bottom: 20px;"><?php esc_html_e( 'L\'e-mail arrive. Faites une partie en attendant !', 'eo-tools' ); ?></p>
			<div id="eo-lp-tictactoe-board" style="display: grid; grid-template-columns: repeat(3, 1fr); gap: 8px; max-width: 240px; margin: 0 auto 20px auto;">
				<?php for ($i = 0; $i < 9; $i++) : ?>
					<div class="eo-lp-ttt-cell" data-index="<?php echo $i; ?>" style="background: rgba(255,255,255,0.1); border: 2px solid <?php echo esc_attr( $accent_color ); ?>; height: 70px; border-radius: 8px; display: flex; align-items: center; justify-content: center; font-size: 32px; font-weight: bold; cursor: pointer; transition: background 0.2s;"></div>
				<?php endfor; ?>
			</div>
			<div id="eo-lp-ttt-status" style="font-size: 16px; font-weight: 600; height: 24px; margin-bottom: 15px;"></div>
			<button type="button" id="eo-lp-ttt-restart" style="background: transparent; border: 1px solid <?php echo esc_attr( $accent_color ); ?>; color: <?php echo esc_attr( $accent_color ); ?>; padding: 6px 12px; border-radius: 4px; cursor: pointer; font-size: 13px; display: none; margin: 0 auto;">Rejouer</button>
			
			<script>
				document.addEventListener('DOMContentLoaded', function() {
					var cells = document.querySelectorAll('.eo-lp-ttt-cell');
					var statusDisplay = document.getElementById('eo-lp-ttt-status');
					var restartBtn = document.getElementById('eo-lp-ttt-restart');
					var board = ['', '', '', '', '', '', '', '', ''];
					var gameActive = true;
					var player = 'X';
					var ai = 'O';

					var winningConditions = [
						[0,1,2], [3,4,5], [6,7,8], [0,3,6], [1,4,7], [2,5,8], [0,4,8], [2,4,6]
					];

					function checkWin(boardState, playerToCheck) {
						for (var i = 0; i < winningConditions.length; i++) {
							var winCondition = winningConditions[i];
							var a = boardState[winCondition[0]];
							var b = boardState[winCondition[1]];
							var c = boardState[winCondition[2]];
							if (a === '' || b === '' || c === '') continue;
							if (a === playerToCheck && b === playerToCheck && c === playerToCheck) return true;
						}
						return false;
					}

					function getAvailableMoves() {
						var moves = [];
						for (var i = 0; i < board.length; i++) {
							if (board[i] === '') moves.push(i);
						}
						return moves;
					}

					function handleResultValidation() {
						if (checkWin(board, player)) {
							statusDisplay.innerHTML = 'Vous avez gagné ! 🎉';
							gameActive = false;
							restartBtn.style.display = 'block';
							return true;
						}
						if (checkWin(board, ai)) {
							statusDisplay.innerHTML = 'L\'ordinateur a gagné ! 🤖';
							gameActive = false;
							restartBtn.style.display = 'block';
							return true;
						}
						if (getAvailableMoves().length === 0) {
							statusDisplay.innerHTML = 'Match nul ! 🤝';
							gameActive = false;
							restartBtn.style.display = 'block';
							return true;
						}
						return false;
					}

					function aiMove() {
						var moves = getAvailableMoves();
						if (moves.length === 0) return;
						// Very basic AI: random move
						var randomMove = moves[Math.floor(Math.random() * moves.length)];
						board[randomMove] = ai;
						cells[randomMove].innerHTML = ai;
						cells[randomMove].style.color = '#e2e8f0';
						
						if (!handleResultValidation()) {
							statusDisplay.innerHTML = 'À vous de jouer !';
						}
					}

					cells.forEach(function(cell) {
						cell.addEventListener('click', function() {
							var index = parseInt(cell.getAttribute('data-index'));
							if (board[index] !== '' || !gameActive) return;
							
							board[index] = player;
							cell.innerHTML = player;
							cell.style.color = '<?php echo esc_js( $accent_color ); ?>';
							
							if (!handleResultValidation()) {
								statusDisplay.innerHTML = 'L\'ordinateur réfléchit...';
								setTimeout(aiMove, 500);
							}
						});
					});

					restartBtn.addEventListener('click', function() {
						board = ['', '', '', '', '', '', '', '', ''];
						gameActive = true;
						statusDisplay.innerHTML = '';
						restartBtn.style.display = 'none';
						cells.forEach(function(cell) {
							cell.innerHTML = '';
						});
					});
				});
			</script>

		<?php elseif ( 'flappybird' === $success_action ) : ?>
			
			<p style="font-size: 15px; margin-bottom: 20px;"><?php esc_html_e( 'L\'e-mail arrive. Cliquez pour faire voler le bloc !', 'eo-tools' ); ?></p>
			<canvas id="eo-lp-flappy-canvas" width="280" height="400" style="background: rgba(0,0,0,0.2); border: 2px solid <?php echo esc_attr( $accent_color ); ?>; border-radius: 8px; display: block; margin: 0 auto; cursor: pointer;"></canvas>
			<div id="eo-lp-flappy-status" style="font-size: 16px; font-weight: 600; margin-top: 15px; display: none;"></div>
			
			<script>
				document.addEventListener('DOMContentLoaded', function() {
					var canvas = document.getElementById('eo-lp-flappy-canvas');
					var ctx = canvas.getContext('2d');
					var statusDisplay = document.getElementById('eo-lp-flappy-status');
					var accentColor = '<?php echo esc_js( $accent_color ); ?>';

					var frames = 0;
					var score = 0;
					var bestScore = 0;
					var currentGameState = 'getReady'; // getReady, game, over
					
					var bird = {
						x: 50, y: 150, width: 20, height: 20,
						gravity: 0.25, jump: 4.6, speed: 0,
						draw: function() {
							ctx.fillStyle = accentColor;
							ctx.fillRect(this.x, this.y, this.width, this.height);
						},
						update: function() {
							this.speed += this.gravity;
							this.y += this.speed;
							if (this.y + this.height >= canvas.height) {
								this.y = canvas.height - this.height;
								currentGameState = 'over';
							}
							if (this.y < 0) {
								this.y = 0;
								this.speed = 0;
							}
						},
						flap: function() {
							this.speed = -this.jump;
						}
					};

					var pipes = {
						position: [], width: 40, gap: 100, dx: 2,
						draw: function() {
							ctx.fillStyle = '#64748b';
							for (var i = 0; i < this.position.length; i++) {
								var p = this.position[i];
								var topY = p.y;
								var bottomY = p.y + this.gap;
								// Top pipe
								ctx.fillRect(p.x, 0, this.width, topY);
								// Bottom pipe
								ctx.fillRect(p.x, bottomY, this.width, canvas.height - bottomY);
							}
						},
						update: function() {
							if (frames % 100 === 0) {
								this.position.push({
									x: canvas.width,
									y: Math.max(20, Math.random() * (canvas.height - this.gap - 20))
								});
							}
							for (var i = 0; i < this.position.length; i++) {
								var p = this.position[i];
								p.x -= this.dx;

								// Collision detection
								if (bird.x + bird.width > p.x && bird.x < p.x + this.width &&
									(bird.y < p.y || bird.y + bird.height > p.y + this.gap)) {
									currentGameState = 'over';
								}

								// Score update
								if (p.x + this.width === bird.x) {
									score++;
									bestScore = Math.max(score, bestScore);
								}

								// Remove pipes that passed
								if (p.x + this.width <= 0) {
									this.position.shift();
									i--;
								}
							}
						},
						reset: function() {
							this.position = [];
						}
					};

					function draw() {
						ctx.clearRect(0, 0, canvas.width, canvas.height);
						bird.draw();
						if (currentGameState === 'game' || currentGameState === 'over') {
							pipes.draw();
						}
						
						// Score
						ctx.fillStyle = '#fff';
						ctx.font = '24px Arial';
						if (currentGameState === 'game') {
							ctx.fillText(score, canvas.width/2 - 10, 40);
						} else if (currentGameState === 'getReady') {
							ctx.font = '20px Arial';
							ctx.fillText('Cliquez pour jouer', canvas.width/2 - 75, canvas.height/2);
						} else if (currentGameState === 'over') {
							ctx.font = '20px Arial';
							ctx.fillText('Score: ' + score, canvas.width/2 - 40, canvas.height/2 - 20);
							ctx.fillText('Cliquez pour rejouer', canvas.width/2 - 85, canvas.height/2 + 20);
						}
					}

					function update() {
						if (currentGameState === 'game') {
							bird.update();
							pipes.update();
						}
					}

					function loop() {
						update();
						draw();
						frames++;
						requestAnimationFrame(loop);
					}

					canvas.addEventListener('click', function() {
						switch (currentGameState) {
							case 'getReady':
								currentGameState = 'game';
								bird.flap();
								break;
							case 'game':
								bird.flap();
								break;
							case 'over':
								pipes.reset();
								score = 0;
								bird.speed = 0;
								bird.y = 150;
								currentGameState = 'getReady';
								break;
						}
					});

					loop();
				});
			</script>

		<?php endif; ?>
	</div>

	<div style="margin-top: 30px; padding-top: 20px; border-top: 1px solid rgba(255,255,255,0.1);">
		<button type="button" id="eo-lp-skip-wait" class="button" style="background: transparent; color: <?php echo esc_attr( $accent_color ); ?>; border: none; font-size: 14px; text-decoration: underline; cursor: pointer; box-shadow: none;">
			<?php esc_html_e( 'J\'ai reçu mon e-mail, me connecter', 'eo-tools' ); ?>
		</button>
	</div>
</div>

<script>
	document.addEventListener('DOMContentLoaded', function() {
		var skipBtn = document.getElementById('eo-lp-skip-wait');
		var waitScreen = document.getElementById('eo-lp-wait-screen');
		var loginFormWrapper = document.getElementById('eo-lp-login-form-wrapper');
		var successMsg = document.getElementById('eo-lp-register-success-msg');

		if (skipBtn && waitScreen && loginFormWrapper) {
			skipBtn.addEventListener('click', function() {
				waitScreen.style.display = 'none';
				loginFormWrapper.style.display = 'block';
				if (successMsg) successMsg.style.display = 'block';
			});
		}
	});
</script>
